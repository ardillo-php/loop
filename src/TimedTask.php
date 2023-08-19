<?php

declare(strict_types=1);

namespace Ardillo;

use React\EventLoop\TimerInterface;
use Closure;

/**
 * Ardillo Task class for React Timers
 */
class TimedTask extends Task implements TimerInterface
{
    protected Closure $fn;
    protected float $interval;
    protected bool $periodic;

    public function __construct(callable $fn, float $interval, bool $periodic = false)
    {
        $this->fn = Closure::fromCallable($fn);
        $this->interval = $interval;
        $this->periodic = $periodic;
    }

    public function onExecute(): void
    {
        call_user_func($this->fn);

        if (!$this->periodic) {
            $this->suspended = true;
        }
    }

    public function getInterval()
    {
        return $this->interval;
    }

    public function getCallback()
    {
        return $this->fn;
    }

    public function isPeriodic()
    {
        return $this->periodic;
    }
}
