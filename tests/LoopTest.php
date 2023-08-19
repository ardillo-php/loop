<?php

declare(strict_types=1);

namespace Ardillo\Tests;

use Ardillo\{
    ReactApp,
    Loop,
};

use PHPUnit\Framework\TestCase;
use React\Promise\{
    PromiseInterface,
    Deferred,
};
use function React\Promise\{
    all,
    resolve,
};

class LoopTest extends TestCase
{
    const PHP_DEFAULT_CHUNK_SIZE = 8192;

    public ReactApp $app;
    public Loop $loop;

    public function setUp(): void
    {
        if (!class_exists('Ardillo\App')) {
            $this->markTestSkipped('Cannot create Ardillo Loop, ardillo extension missing');
        }

        $this->app = new ReactApp;
        $this->loop = $this->app->getLoop();
    }

    public function createSocketPair()
    {
        $domain = (DIRECTORY_SEPARATOR === '\\') ? STREAM_PF_INET : STREAM_PF_UNIX;
        $sockets = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        foreach ($sockets as $socket) {
            if (function_exists('stream_set_read_buffer')) {
                stream_set_read_buffer($socket, 0);
            }
        }

        return $sockets;
    }

    public function testAllAsync(): void
    {
        $tests = all([
            $this->_testFutureTickTasks(),
            $this->_testPeriodicTasks(),
            $this->_testPeriodicTasksWithNegativeInterval(),
            $this->_testAddReadStreamTriggersWhenSocketReceivesData(),
            $this->_testAddReadStreamTriggersWhenSocketCloses(),
            $this->_testAddWriteStreamTriggersWhenSocketConnectionSucceeds(),
            $this->_testAddWriteStreamTriggersWhenSocketConnectionRefused(),
            $this->_testRemoveReadAndWriteStreamFromLoopOnceResourceClosesEndsLoop(),
            $this->_testRemoveReadAndWriteStreamFromLoopOnceResourceClosesOnEndOfFileEndsLoop(),
            $this->_testRemoveReadAndWriteStreamFromLoopWithClosingResourceEndsLoop(),
            $this->_testRemoveInvalid(),
            $this->_testIgnoreRemovedCallback(),
            $this->_testRemoveSignalNotRegisteredIsNoOp(),
            $this->_testSignal(),
        ]);

        $this->assertInstanceof(PromiseInterface::class, $tests);

        $tests->then($this->loop->stop(...));

        $this->app->run();
    }

    public function _testFutureTickTasks(): PromiseInterface
    {
        $deferred = new Deferred;

        $this->loop->futureTick(function () use ($deferred): void {
            $this->assertTrue(true);

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    public function _testPeriodicTasks(): PromiseInterface
    {
        $deferred = new Deferred;

        $count = 0;

        $timer = $this->loop->addPeriodicTimer(0.01, function () use (&$count, &$timer, $deferred): void {
            $count++;

            if ($count == 5) {
                $this->assertTrue(true);
                $this->loop->cancelTimer($timer);

                $deferred->resolve();
            }
        });

        return $deferred->promise();
    }

    public function _testPeriodicTasksWithNegativeInterval(): PromiseInterface
    {
        $deferred = new Deferred;

        $this->loop->addPeriodicTimer(-1, function () use ($deferred): void {
            $this->assertTrue(true);

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    public function _testAddReadStreamTriggersWhenSocketReceivesData(): PromiseInterface
    {
        $deferred = new Deferred;

        [$input, $output] = $this->createSocketPair();

        $called = 0;
        $this->loop->addReadStream($input, function () use ($input, $deferred): void {
            $data = fread($input, 1024);
            $this->assertEquals("foo\n", $data);
            $this->loop->removeReadStream($input);

            $deferred->resolve();
        });

        fwrite($output, "foo\n");

        return $deferred->promise();
    }

    public function _testAddReadStreamTriggersWhenSocketCloses(): PromiseInterface
    {
        $deferred = new Deferred;

        [$input, $output] = $this->createSocketPair();

        $called = 0;
        $this->loop->addReadStream($input, function () use ($input, $deferred): void {
            $this->assertTrue(feof($input));
            $this->loop->removeReadStream($input);

            $deferred->resolve();
        });

        fclose($output);

        return $deferred->promise();
    }

    public function _testAddWriteStreamTriggersWhenSocketConnectionSucceeds(): PromiseInterface
    {
        $deferred = new Deferred;

        $server = stream_socket_server('127.0.0.1:0');

        $errno = $errstr = null;
        $connecting = stream_socket_client(stream_socket_get_name($server, false), $errno, $errstr, 0, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);

        $this->loop->addWriteStream($connecting, function () use ($connecting, $deferred): void {
            $this->assertIsResource($connecting);
            $this->loop->removeWriteStream($connecting);

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    public function _testAddWriteStreamTriggersWhenSocketConnectionRefused(): PromiseInterface
    {
        $deferred = new Deferred;

        $errno = $errstr = null;
        if (@stream_socket_client('127.0.0.1:1', $errno, $errstr, 10.0) !== false || (defined('SOCKET_ECONNREFUSED') && $errno !== SOCKET_ECONNREFUSED)) {
            $this->markTestSkipped('Expected host to refuse connection, but got error ' . $errno . ': ' . $errstr);
        }

        $connecting = stream_socket_client('127.0.0.1:1', $errno, $errstr, 0, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);

        $timeout = $this->loop->addTimer(10.0, function () use ($connecting, $deferred) {
            $this->loop->removeWriteStream($connecting);

            $deferred->resolve();
        });

        $this->loop->addWriteStream($connecting, function () use ($connecting, $deferred, $timeout): void {
            $this->loop->cancelTimer($timeout);

            $this->assertIsResource($connecting);
            $this->loop->removeWriteStream($connecting);

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    public function _testRemoveReadAndWriteStreamFromLoopOnceResourceClosesEndsLoop(): PromiseInterface
    {
        $deferred = new Deferred;

        [$stream, $other] = $this->createSocketPair();
        stream_set_blocking($stream, false);
        stream_set_blocking($other, false);

        $this->loop->addWriteStream($stream, function (): void {});

        $this->loop->addReadStream($stream, function ($stream) use ($deferred): void {
            $this->assertIsResource($stream);

            $this->loop->removeReadStream($stream);
            $this->loop->removeWriteStream($stream);

            fclose($stream);

            $deferred->resolve();
        });

        fclose($other);

        return $deferred->promise();
    }

    public function _testRemoveReadAndWriteStreamFromLoopOnceResourceClosesOnEndOfFileEndsLoop(): PromiseInterface
    {
        $deferred = new Deferred;

        [$stream, $other] = $this->createSocketPair();
        stream_set_blocking($stream, false);
        stream_set_blocking($other, false);

        $this->loop->addWriteStream($stream, function (): void {});

        $this->loop->addReadStream($stream, function ($stream) use ($deferred): void {
            $this->assertIsResource($stream);

            $data = fread($stream, 1024);
            $this->assertNotEquals('', $data);

            $this->loop->removeReadStream($stream);
            $this->loop->removeWriteStream($stream);

            fclose($stream);

            $deferred->resolve();
        });

        fwrite($other, str_repeat('.', static::PHP_DEFAULT_CHUNK_SIZE));

        $this->loop->addTimer(0.01, function () use ($other): void {
            fclose($other);
        });

        return $deferred->promise();
    }

    public function _testRemoveReadAndWriteStreamFromLoopWithClosingResourceEndsLoop(): PromiseInterface
    {
        $deferred = new Deferred;

        [$stream] = $this->createSocketPair();
        stream_set_blocking($stream, false);

        $this->loop->addWriteStream($stream, function (): void {});

        $this->loop->addReadStream($stream, function ($stream) use ($deferred): void {
            $this->assertIsResource($stream);

            $this->loop->removeReadStream($stream);
            $this->loop->removeWriteStream($stream);

            fclose($stream);

            $deferred->resolve();
        });

        return $deferred->promise();
    }

    public function _testRemoveInvalid(): PromiseInterface
    {
        [$stream] = $this->createSocketPair();

        $this->loop->removeReadStream($stream);
        $this->loop->removeWriteStream($stream);

        $this->assertTrue(true);

        return resolve();
    }

    public function _testIgnoreRemovedCallback(): PromiseInterface
    {
        $deferred = new Deferred;

        [$input1, $output1] = $this->createSocketPair();
        [$input2, $output2] = $this->createSocketPair();

        $called = false;

        $this->loop->addReadStream($input1, function ($stream) use (&$called, $deferred, $input2): void {
            $called = true;

            $this->assertIsResource($stream);
            $this->assertIsResource($input2);

            $this->loop->removeReadStream($stream);
            $this->loop->removeReadStream($input2);

            $deferred->resolve();
        });

        $this->loop->addReadStream($input2, function () use (&$called): void {
            $this->assertFalse($called);
        });

        fwrite($output1, "foo\n");
        fwrite($output2, "foo\n");

        return $deferred->promise();
    }

    public function _testRemoveSignalNotRegisteredIsNoOp(): PromiseInterface
    {
        $this->loop->removeSignal(2, function (): void {});
        $this->assertTrue(true);

        return resolve();
    }

    public function _testSignal(): PromiseInterface
    {
        $deferred = new Deferred;

        $this->loop->addSignal(ReactApp::SIGALRM, function (int $signal) use ($deferred): void {
            $this->assertEquals(ReactApp::SIGALRM, $signal);

            $deferred->resolve();
        });

        $this->app->onSignal(ReactApp::SIGALRM);

        return $deferred->promise();
    }
}
