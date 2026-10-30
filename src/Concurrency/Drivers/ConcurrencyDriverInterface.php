<?php

declare(strict_types=1);

namespace Hyperdrive\Concurrency\Drivers;

interface ConcurrencyDriverInterface
{
    /**
     * Execute multiple operations and return the first successful result
     * Ignores failures until one succeeds or all fail
     */
    public function any(array $operations): mixed;

    /**
     * Execute multiple operations and return the result of whichever completes first
     */
    public function race(array $operations): mixed;

    /**
     * Process an array of items concurrently using the mapper function
     * Preserves the original order of items
     */
    public function map(array $items, callable $mapper): array;

    /**
     * Run every operation (concurrently where the driver can) and return
     * all results under their original keys, in input order. The first
     * operation to throw has its exception rethrown.
     *
     * @param array<array-key, callable> $operations
     * @return array<array-key, mixed>
     */
    public function all(array $operations): array;
}
