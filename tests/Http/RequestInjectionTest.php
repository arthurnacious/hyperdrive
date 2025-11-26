<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestInjectionTest extends TestCase
{
    public function test_it_injects_data_immutably(): void
    {
        $request = new Request();

        $newRequest = $request->withInjected('user_id', 'user_123');
        $newRequest = $newRequest->withInjected('user_roles', ['admin', 'user']);

        $this->assertEquals('user_123', $newRequest->injected('user_id'));
        $this->assertEquals(['admin', 'user'], $newRequest->injected('user_roles'));
        $this->assertFalse($request->hasInjected('user_id')); // Original unchanged
    }

    public function test_it_checks_injected_data_existence(): void
    {
        $request = new Request(injected: ['user_id' => 'user_123']);

        $this->assertTrue($request->hasInjected('user_id'));
        $this->assertFalse($request->hasInjected('nonexistent'));
    }

    public function test_it_gets_all_injected_data(): void
    {
        $injected = ['user_id' => 'user_123', 'tenant_id' => 'tenant_456'];
        $request = new Request(injected: $injected);

        $this->assertEquals($injected, $request->allInjected());
    }

    public function test_magic_getter_accesses_injected_data(): void
    {
        $request = new Request(
            query: ['name' => 'query_name'],
            injected: ['name' => 'injected_name']
        );

        // Should prefer input data over injected data
        $this->assertEquals('query_name', $request->name);
    }

    public function test_magic_isset_checks_injected_data(): void
    {
        $request = new Request(
            query: ['email' => 'test@example.com'],
            injected: ['user_id' => 'user_123']
        );

        $this->assertTrue(isset($request->email)); // From query
        $this->assertTrue(isset($request->user_id)); // From injected
        $this->assertFalse(isset($request->nonexistent));
    }
}
