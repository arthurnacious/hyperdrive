<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Events;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Attributes\Events\Listener;
use Hyperdrive\Container\Container;
use Hyperdrive\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

class UserRegistered
{
    public function __construct(public string $email) {}
}

class WelcomeEmailListener
{
    public array $calls = [];

    #[Listener(UserRegistered::class)]
    public function handle(UserRegistered $event): void
    {
        $this->calls[] = $event->email;
    }
}

#[Module(listeners: [WelcomeEmailListener::class])]
class UsersModule {}

#[Module(imports: [UsersModule::class])]
class RootModule {}

class ModuleEventsTest extends TestCase
{
    public function test_module_can_register_listeners(): void
    {
        $moduleRegistry = new ModuleRegistry();

        $moduleRegistry->register(UsersModule::class);

        $this->assertEquals([WelcomeEmailListener::class], $moduleRegistry->getListeners(UsersModule::class));
    }

    public function test_registering_a_module_automatically_wires_its_listeners_to_the_dispatcher(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $moduleRegistry = new ModuleRegistry();
        $moduleRegistry->setEventDispatcher($dispatcher);

        // No manual registerListenerClass() call here - just registering
        // the module is enough.
        $moduleRegistry->register(UsersModule::class);
        $dispatcher->dispatch(new UserRegistered('dana@example.com'));

        $listener = $container->get(WelcomeEmailListener::class);
        $this->assertSame(['dana@example.com'], $listener->calls);
    }

    public function test_listeners_declared_in_an_imported_module_are_also_wired(): void
    {
        $container = new Container();
        $dispatcher = new EventDispatcher($container);
        $moduleRegistry = new ModuleRegistry();
        $moduleRegistry->setEventDispatcher($dispatcher);

        $moduleRegistry->register(RootModule::class);
        $dispatcher->dispatch(new UserRegistered('dana@example.com'));

        $listener = $container->get(WelcomeEmailListener::class);
        $this->assertSame(['dana@example.com'], $listener->calls);
    }
}
