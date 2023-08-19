<?php

declare(strict_types=1);

namespace Ardillo;

use Closure;

/**
 * Ardillo Stream Handler
 */
class StreamHandler
{
    /** @var resource */
    public $stream;

    public Closure $handler;

    /**
     * Constructs a new StreamHandler
     *
     * @param resource $stream Stream
     * @param callable $handler Handler
     */
    public function __construct($stream, callable $handler)
    {
        $this->stream = $stream;
        $this->handler = Closure::fromCallable($handler);
    }
}
