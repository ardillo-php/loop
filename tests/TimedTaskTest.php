<?php

declare(strict_types=1);

namespace Ardillo\Tests;

use Ardillo\TimedTask;

use PHPUnit\Framework\TestCase;

class TimedTaskTest extends TestCase
{
    public function testGetters(): void
    {
        $foo = function () {};
        $task = new TimedTask($foo, 3.14, true);

        $this->assertSame($foo, $task->getCallback());
        $this->assertSame(3.14, $task->getInterval());
        $this->assertTrue($task->isPeriodic());
    }

    public function testExecute(): void
    {
        $this->expectOutputString('timed-task-test');

        $foo = function () {
            echo 'timed-task-test';
        };

        $task = new TimedTask($foo, 1, false);
        $task->onExecute();
    }
}
