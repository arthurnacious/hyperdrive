<?php

declare(strict_types=1);

namespace Hyperdrive\Events;

use Hyperdrive\Attributes\Events\Listener;
use Hyperdrive\Container\Container;

class EventDispatcher
{
    /** @var array<class-string, array<array{class: class-string, method: string}>> */
    private array $listeners = [];

    public function __construct(private Container $container) {}

    /**
     * Reflects a listener class for #[Listener(EventClass::class)]-decorated
     * methods and registers each one. A single class can listen for several
     * event types via several methods.
     */
    public function registerListenerClass(string $listenerClass): void
    {
        $reflection = new \ReflectionClass($listenerClass);

        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Listener::class) as $attribute) {
                $listenerAttribute = $attribute->newInstance();

                $this->listeners[$listenerAttribute->event][] = [
                    'class' => $listenerClass,
                    'method' => $method->getName(),
                ];
            }
        }
    }

    /**
     * Calls every listener registered for $event's class, in registration
     * order, resolving each listener instance through the container. If a
     * listener throws, the exception propagates immediately - remaining
     * listeners for this dispatch do not run.
     */
    public function dispatch(object $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $instance = $this->container->get($listener['class']);
            $method = $listener['method'];
            $instance->$method($event);
        }
    }
}
