<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\JsonResponse;
use Hyperdrive\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_it_creates_json_response(): void
    {
        $data = ['message' => 'Success', 'user' => ['id' => 123]];
        $response = JsonResponse::json($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertEquals(json_encode($data), $response->getContent());
    }

    public function test_json_response_constructor(): void
    {
        $data = ['status' => 'ok'];
        $response = new JsonResponse($data, 200, ['X-Custom' => 'value']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertEquals('value', $response->getHeaders()['X-Custom']);
        $this->assertEquals(json_encode($data), $response->getContent());
    }

    public function test_send_does_not_warn_when_headers_are_already_sent(): void
    {
        // Reproduces running a front controller directly via plain `php
        // script.php` (as opposed to php-fpm/apache/`php -S`) after
        // something else has already echoed - e.g. Hyperdrive::boot()'s
        // dev-mode banner. headers_sent() can't actually become true
        // inside a Pest/PHPUnit test (they wrap everything in their own
        // output buffer), so this spawns a real subprocess instead, the
        // only way to faithfully reproduce it.
        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = <<<PHP
            <?php
            require '{$autoloadPath}';
            echo 'output before send' . PHP_EOL;
            (new Hyperdrive\Http\Response('body content', 200, ['X-Custom' => 'value']))->send();
            PHP;

        $scriptPath = tempnam(sys_get_temp_dir(), 'hyperdrive_response_test_') . '.php';
        file_put_contents($scriptPath, $script);

        try {
            $output = shell_exec(sprintf('php %s 2>&1', escapeshellarg($scriptPath)));
        } finally {
            unlink($scriptPath);
        }

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringContainsString('output before send', $output);
        $this->assertStringContainsString('body content', $output);
    }
}
