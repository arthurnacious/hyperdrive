<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Attributes;

use Hyperdrive\Attributes\Middleware;
use PHPUnit\Framework\TestCase;

class MiddlewareAttributeTest extends TestCase
{
    public function test_middleware_attribute_can_be_created_with_middlewares(): void
    {
        $middlewares = ['AuthMiddleware', 'AdminMiddleware'];
        $attribute = new Middleware(middlewares: $middlewares);

        $this->assertEquals($middlewares, $attribute->middlewares);
    }

    public function test_middleware_attribute_can_be_empty(): void
    {
        $attribute = new Middleware(middlewares: []);

        $this->assertEmpty($attribute->middlewares);
    }

    public function test_middleware_attribute_can_be_used_on_class(): void
    {
        // Test class with attribute
        $className = 'TestController';
        $middlewares = ['AuthMiddleware'];

        $reflection = new \ReflectionClass(new class {
            #[Middleware(middlewares: ['AuthMiddleware'])]
            public function testMethod() {}
        });

        $attributes = $reflection->getMethod('testMethod')->getAttributes(Middleware::class);

        $this->assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(['AuthMiddleware'], $attribute->middlewares);
    }

    public function test_middleware_attribute_accepts_array_type(): void
    {
        $attribute = new Middleware(middlewares: ['SomeMiddleware']);

        $this->assertIsArray($attribute->middlewares);
        $this->assertContains('SomeMiddleware', $attribute->middlewares);
    }
}
