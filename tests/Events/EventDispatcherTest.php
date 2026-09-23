<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Events;

use Hyperdrive\Attributes\Events\Listener;
use Hyperdrive\Container\Container;
use Hyperdrive\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

class ProjectCreated
{
    public function __construct(public string $name) {}
}

class ProjectDeleted
{
    public function __construct(public string $name) {}
}

class ProjectListeners
{
    /** @var string[] */
    public array $calls = [];

    #[Listener(ProjectCreated::class)]
    public function logCreated(ProjectCreated $event): void
    {
        $this->calls[] = "created:{$event->name}";
    }

    #[Listener(ProjectDeleted::class)]
    public function logDeleted(ProjectDeleted $event): void
    {
        $this->calls[] = "deleted:{$event->name}";
    }
}

class SecondProjectCreatedListener
{
    public array $calls = [];

    #[Listener(ProjectCreated::class)]
    public function handle(ProjectCreated $event): void
    {
        $this->calls[] = "second:{$event->name}";
    }
}

class ThrowingListener
{
    #[Listener(ProjectCreated::class)]
    public function handle(ProjectCreated $event): void
    {
        throw new \RuntimeException('listener blew up');
    }
}

class NeverCalledListener
{
    public bool $called = false;

    #[Listener(ProjectCreated::class)]
    public function handle(ProjectCreated $event): void
    {
        $this->called = true;
    }
}

class EventDispatcherTest extends TestCase
{
    public function test_dispatch_calls_the_listener_method_for_that_event(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $dispatcher->registerListenerClass(ProjectListeners::class);
        $dispatcher->dispatch(new ProjectCreated('Acme Site'));

        $listener = $container->get(ProjectListeners::class);
        $this->assertSame(['created:Acme Site'], $listener->calls);
    }

    public function test_a_listener_class_can_handle_several_event_types(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $dispatcher->registerListenerClass(ProjectListeners::class);
        $dispatcher->dispatch(new ProjectCreated('Acme Site'));
        $dispatcher->dispatch(new ProjectDeleted('Old Site'));

        $listener = $container->get(ProjectListeners::class);
        $this->assertSame(['created:Acme Site', 'deleted:Old Site'], $listener->calls);
    }

    public function test_multiple_listener_classes_for_the_same_event_all_run_in_registration_order(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $dispatcher->registerListenerClass(ProjectListeners::class);
        $dispatcher->registerListenerClass(SecondProjectCreatedListener::class);
        $dispatcher->dispatch(new ProjectCreated('Acme Site'));

        $first = $container->get(ProjectListeners::class);
        $second = $container->get(SecondProjectCreatedListener::class);
        $this->assertSame(['created:Acme Site'], $first->calls);
        $this->assertSame(['second:Acme Site'], $second->calls);
    }

    public function test_dispatching_an_event_with_no_listeners_is_a_no_op(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $dispatcher->dispatch(new ProjectCreated('Acme Site'));

        $this->assertTrue(true); // no exception thrown
    }

    public function test_a_listener_exception_propagates_and_stops_later_listeners(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $dispatcher->registerListenerClass(ThrowingListener::class);
        $dispatcher->registerListenerClass(NeverCalledListener::class);

        try {
            $dispatcher->dispatch(new ProjectCreated('Acme Site'));
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('listener blew up', $e->getMessage());
        }

        $neverCalled = $container->get(NeverCalledListener::class);
        $this->assertFalse($neverCalled->called);
    }

    public function test_listeners_are_resolved_through_the_container(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);

        $dispatcher->registerListenerClass(ProjectListeners::class);
        $dispatcher->dispatch(new ProjectCreated('Acme Site'));

        // Same instance the container would hand anyone else - proves
        // listeners aren't constructed bypassing the container (so they
        // can have their own injected dependencies).
        $this->assertSame($container->get(ProjectListeners::class), $container->get(ProjectListeners::class));
    }
}
