<?php

declare(strict_types=1);

namespace IRJalali\Core\Events;

/**
 * Typed event dispatcher. Core NEVER hard-codes reactions to
 * domain events — listeners are registered by app/plugins/modules.
 */
final class Dispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(object $event): object
    {
        $name = $event::class;
        foreach ($this->listeners[$name] ?? [] as $listener) {
            $listener($event);
            if ($event instanceof StoppableEvent && $event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
}
