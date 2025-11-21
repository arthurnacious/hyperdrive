<?php

declare(strict_types=1);

namespace Hyperdrive\Concurrency\Drivers;

class SyncConcurrencyDriver implements ConcurrencyDriverInterface
{
    public function any(array $operations): mixed
    {
        $exceptions = [];

        foreach ($operations as $operation) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }

        throw new \RuntimeException('All operations failed', 0, $exceptions[0] ?? null);
    }

    public function race(array $operations): mixed
    {
        // In sync mode, we can't truly race, so we execute in order
        // But if any operation fails, we should throw immediately
        foreach ($operations as $operation) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                // In a true race, we'd continue to see if others complete
                // But in sync mode, we have to choose one behavior
                // Let's throw immediately to match the expected race behavior
                throw $e;
            }
        }

        throw new \RuntimeException('No operations provided for race');
    }

    public function map(array $items, callable $mapper): array
    {
        // In sync mode, just map sequentially
        $results = [];
        foreach ($items as $item) {
            $results[] = $mapper($item);
        }
        return $results;
    }
}
