<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Container;

use Hyperdrive\Container\Container;
use PHPUnit\Framework\TestCase;

class ContainerTestService {}

class ContainerTest extends TestCase
{
    public function test_it_can_resolve_simple_class(): void
    {
        $container = new Container();

        $instance = $container->get(ContainerTestService::class);

        $this->assertInstanceOf(ContainerTestService::class, $instance);
    }
}
