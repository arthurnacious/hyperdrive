<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

abstract class AbstractServerDriver extends AbstractDriver
{
    protected string $protocol = 'http';
    protected array $serverOptions = [];

    public function setServerOptions(array $options): void
    {
        $this->serverOptions = $options;
        $this->detectProtocol();
    }

    public function listen(int $port = 3000, string $host = '0.0.0.0'): void
    {
        if (!$this->running) {
            throw new \RuntimeException('Driver must be booted before listening');
        }

        $this->startServer($port, $host);
    }

    abstract protected function startServer(int $port, string $host): void;

    protected function getServerUrl(string $host, int $port): string
    {
        return "{$this->protocol}://{$host}:{$port}";
    }

    protected function logServerStart(string $driverName, string $url): void
    {
        echo "🚀 {$driverName} driver listening on {$url}\n";

        if ($driverName === 'Roadstar') {
            echo "📝 Note: Roadstar uses traditional PHP server. Configure your web server to point to public/index.php\n";
        }
    }

    private function detectProtocol(): void
    {
        // Check if SSL options are provided
        if (isset($this->serverOptions['ssl_cert_file']) && isset($this->serverOptions['ssl_key_file'])) {
            $this->protocol = 'https';
        }
    }
}
