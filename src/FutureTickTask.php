<?php

declare(strict_types=1);

namespace Ardillo;

use Closure;

/**
 * Ardillo Task class for adhoc React workloads
 */
class FutureTickTask extends Task
{
    protected Closure $fn;

    public function __construct(callable $fn)
    {
        $this->fn = Closure::fromCallable($fn);
    }

    public function onExecute(): void
    {
        call_user_func($this->fn);
    }
}
