<?php

declare(strict_types=1);

namespace Ardillo;

use React\EventLoop\Loop as ReactLoop;

/**
 * Ardillo App for React programs
 */
class ReactApp extends App
{
    protected Loop $loop;

    public function __construct()
    {
        $this->loop = new Loop;
        $this->loop->setApp($this);

        ReactLoop::set($this->loop);

        $this->OnInit();
    }

    public function onSignal(int $signal): void
    {
        if (isset($this->loop->signals[$signal])) {
            call_user_func($this->loop->signals[$signal], $signal);
        }
    }

    public function getLoop(): Loop
    {
        return $this->loop;
    }

    protected function OnInit(): void
    {
    }
}
