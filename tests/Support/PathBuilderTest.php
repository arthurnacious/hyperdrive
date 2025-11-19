<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Support;

use Hyperdrive\Support\PathBuilder;
use PHPUnit\Framework\TestCase;

class PathBuilderTest extends TestCase
{
    public function test_it_builds_paths_correctly(): void
    {
        $this->assertEquals('/', PathBuilder::build('', ''));
        $this->assertEquals('/api', PathBuilder::build('api', ''));
        $this->assertEquals('/users', PathBuilder::build('', 'users'));
        $this->assertEquals('/api/users', PathBuilder::build('api', 'users'));
        $this->assertEquals('/api/users', PathBuilder::build('/api', '/users'));
        $this->assertEquals('/api/users', PathBuilder::build('api/', '/users/'));
    }

    public function test_it_handles_complex_paths(): void
    {
        $this->assertEquals('/ws/chat/123', PathBuilder::build('ws', 'chat/123'));
        $this->assertEquals('/api/v1/users', PathBuilder::build('api/v1', 'users'));
    }
}
