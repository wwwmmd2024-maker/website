<?php

declare(strict_types=1);

namespace IRJalali\Core\Events;

abstract class StoppableEvent
{
    private bool $stopped = false;

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
