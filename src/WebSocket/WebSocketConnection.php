<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

interface WebSocketConnection
{
    public function send(array $data): void;
    public function close(): void;
    public function getId(): string;
    public function getAttributes(): array;
    public function setAttribute(string $key, mixed $value): void;
    public function getAttribute(string $key, mixed $default = null): mixed;
}
