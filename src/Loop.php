<?php

declare(strict_types=1);

namespace Ardillo;

use React\EventLoop\{
    LoopInterface,
    TimerInterface,
};
use BadMethodCallException;
use InvalidArgumentException;

/**
 * An Ardillo based React event loop
 */
final class Loop implements LoopInterface, StreamEventHandler
{
    protected App $app;

    /** @var array<int, StreamHandler> */
    private $readStreams = [];

    /** @var array<int, StreamHandler> */
    private $writeStreams = [];

    /** @var array<int, int> */
    private $streamDescriptors = [];

    private bool $running;

    /** @var array<int, callable> */
    public $signals = [];

    /**
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        if (!class_exists('Ardillo\App')) {
            throw new BadMethodCallException('Cannot create Ardillo Loop, ardillo extension missing');
        }
    }

    /**
     * @internal
     */
    public function setApp(App $app): void
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, $listener): void
    {
        $fd = $this->app->addReadStream($stream, $this);
        $this->readStreams[$fd] = new StreamHandler($stream, $listener);
        $this->streamDescriptors[(int)$stream] = $fd;
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, $listener): void
    {
        $fd = $this->app->addWriteStream($stream, $this);
        $this->writeStreams[$fd] = new StreamHandler($stream, $listener);
        $this->streamDescriptors[(int)$stream] = $fd;
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream): void
    {
        $streamId = (int)$stream;

        if (!isset($this->streamDescriptors[$streamId])) {
            return;
        }

        $fd = $this->streamDescriptors[$streamId];
        $this->app->removeReadStream($fd);
        unset($this->readStreams[$fd]);

        if (!isset($this->writeStreams[$fd])) {
            unset($this->streamDescriptors[$streamId]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream): void
    {
        $streamId = (int)$stream;

        if (!isset($this->streamDescriptors[$streamId])) {
            return;
        }

        $fd = $this->streamDescriptors[$streamId];
        $this->app->removeWriteStream($fd);
        unset($this->writeStreams[$fd]);

        if (!isset($this->readStreams[$fd])) {
            unset($this->streamDescriptors[$streamId]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, $callback): TimedTask
    {
        $task = new TimedTask($callback, $interval);
        $this->app->scheduleTask($task, $this->convertFloatSecondsToMilliseconds($interval));

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, $callback): TimedTask
    {
        $task = new TimedTask($callback, $interval, true);
        $this->app->scheduleTask($task, $this->convertFloatSecondsToMilliseconds($interval));

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer): void
    {
        assert($timer instanceof TimedTask);

        $timer->suspended = true;
    }

    /**
     * {@inheritdoc}
     */
    public function futureTick($listener): void
    {
        $this->app->scheduleTask(new FutureTickTask($listener), 0);
    }

    public function onReadEvent(int $fd): void
    {
        if (isset($this->readStreams[$fd])) {
            call_user_func($this->readStreams[$fd]->handler, $this->readStreams[$fd]->stream);
        }
    }

    public function onWriteEvent(int $fd): void
    {
        if (isset($this->writeStreams[$fd])) {
            call_user_func($this->writeStreams[$fd]->handler, $this->writeStreams[$fd]->stream);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addSignal($signal, $listener): void
    {
        $this->app->addSignal($signal);
        $this->signals[$signal] = $listener;
    }

    /**
     * {@inheritdoc}
     */
    public function removeSignal($signal, $listener): void
    {
        $this->app->removeSignal($signal);
        unset($this->signals[$signal]);
    }

    /**
     * {@inheritdoc}
     * @codeCoverageIgnore
     */
    public function run(): void
    {
        $this->running = true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->running = false;
        $this->app->stop();
    }

    /**
     * @codeCoverageIgnore
     */
    public function exit(): void
    {
        if (!$this->running) {
            return;
        }

        $this->stop();
    }

    /**
     * Time interval conversion helper, courtesy of the official React Event Loop implementation:
     *     https://github.com/reactphp/event-loop/blob/v1.4.0/src/ExtUvLoop.php#L325
     *
     * @param float $interval
     * @return int
     */
    private function convertFloatSecondsToMilliseconds($interval): int
    {
        if ($interval < 0) {
            return 0;
        }

        $maxValue = (int)(PHP_INT_MAX / 1000);
        $intInterval = (int)$interval;

        // @codeCoverageIgnoreStart
        if ((($intInterval <= 0) && ($interval > 1)) || ($intInterval >= $maxValue)) {
            throw new InvalidArgumentException(
                "Interval overflow, value must be lower than '{$maxValue}', but '{$interval}' passed."
            );
        }
        // @codeCoverageIgnoreEnd

        return (int)floor($interval * 1000);
    }
}
