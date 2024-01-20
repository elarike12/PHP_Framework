<?php

/**
 * The EventDispatcher class provides a central component for dispatching events
 * within the application. It implements the Psr\EventDispatcher\EventDispatcherInterface
 * and serves as a bridge between event listeners and the events themselves.
 *
 * Copyright @ Elar Must.
 */

namespace Framework\Event;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

class EventDispatcher implements EventDispatcherInterface {
    /**
     * @param ListenerProviderInterface $eventListenerProvider
     */
    public function __construct(private ListenerProviderInterface $eventListenerProvider) {
    }

    /**
     * Dispatch event
     *
     * @param object $event Event object.
     *
     * @return object Returns a modified event.
     */
    public function dispatch(object $event): object {
        $stoppable = false;
        if ($event instanceof StoppableEventInterface) {
            $stoppable = true;
        }

        foreach ($this->eventListenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);
            if ($stoppable && $event->isPropagationStopped()) {
                return $event;
            }
        }

        return $event;
    }
}
