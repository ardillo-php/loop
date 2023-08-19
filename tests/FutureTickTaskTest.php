<?php

declare(strict_types=1);

namespace Ardillo\Tests;

use Ardillo\FutureTickTask;

use PHPUnit\Framework\TestCase;

class FutureTickTaskTest extends TestCase
{
    public function testExecute(): void
    {
        $this->expectOutputString('timed-task-test');

        $foo = function () {
            echo 'timed-task-test';
        };

        $task = new FutureTickTask($foo);
        $task->onExecute();
    }
}
