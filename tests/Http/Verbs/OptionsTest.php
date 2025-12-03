<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Attributes\Http;

use Hyperdrive\Attributes\Http\Verbs\Options;
use PHPUnit\Framework\TestCase;

class OptionsTest extends TestCase
{
    public function test_options_attribute_can_be_created(): void
    {
        $attribute = new Options('/test');

        $this->assertInstanceOf(Options::class, $attribute);
        $this->assertEquals('/test', $attribute->path);
    }

    public function test_options_attribute_with_empty_path(): void
    {
        $attribute = new Options();

        $this->assertEquals('', $attribute->path);
    }
}
