<?php

declare(strict_types=1);

namespace Hyperdrive\Concurrency\Drivers;

class OpenSwooleConcurrencyDriver implements ConcurrencyDriverInterface
{
    public function any(array $operations): mixed
    {
        // Check if we're already in a coroutine context
        if (\OpenSwoole\Coroutine::getCid() > 0) {
            return $this->executeAnyInCoroutine($operations);
        }

        // Execute in new coroutine context
        return \OpenSwoole\Coroutine\run(function () use ($operations) {
            return $this->executeAnyInCoroutine($operations);
        });
    }

    public function race(array $operations): mixed
    {
        if (\OpenSwoole\Coroutine::getCid() > 0) {
            return $this->executeRaceInCoroutine($operations);
        }

        return \OpenSwoole\Coroutine\run(function () use ($operations) {
            return $this->executeRaceInCoroutine($operations);
        });
    }

    public function map(array $items, callable $mapper): array
    {
        if (\OpenSwoole\Coroutine::getCid() > 0) {
            return $this->executeMapInCoroutine($items, $mapper);
        }

        return \OpenSwoole\Coroutine\run(function () use ($items, $mapper) {
            return $this->executeMapInCoroutine($items, $mapper);
        });
    }

    private function executeAnyInCoroutine(array $operations): mixed
    {
        $channel = new \OpenSwoole\Coroutine\Channel(1);
        $exceptions = [];
        $completed = 0;

        foreach ($operations as $operation) {
            \OpenSwoole\Coroutine::create(function () use ($operation, $channel, &$exceptions, &$completed, $operations) {
                try {
                    $result = $operation();
                    $channel->push(['type' => 'success', 'value' => $result]);
                } catch (\Throwable $e) {
                    $exceptions[] = $e;
                    $completed++;

                    // If all operations completed and all failed
                    if ($completed === count($operations)) {
                        $channel->push(['type' => 'error', 'exceptions' => $exceptions]);
                    }
                }
            });
        }

        $result = $channel->pop();

        if ($result['type'] === 'success') {
            return $result['value'];
        }

        throw new \RuntimeException('All operations failed', 0, $result['exceptions'][0] ?? null);
    }

    private function executeRaceInCoroutine(array $operations): mixed
    {
        $channel = new \OpenSwoole\Coroutine\Channel(1);

        foreach ($operations as $operation) {
            \OpenSwoole\Coroutine::create(function () use ($operation, $channel) {
                try {
                    $result = $operation();
                    $channel->push(['type' => 'success', 'value' => $result]);
                } catch (\Throwable $e) {
                    $channel->push(['type' => 'error', 'exception' => $e]);
                }
            });
        }

        $result = $channel->pop();

        if ($result['type'] === 'success') {
            return $result['value'];
        }

        throw $result['exception'];
    }

    private function executeMapInCoroutine(array $items, callable $mapper): array
    {
        $results = [];
        $channel = new \OpenSwoole\Coroutine\Channel(count($items));

        foreach ($items as $index => $item) {
            \OpenSwoole\Coroutine::create(function () use ($item, $mapper, $index, $channel) {
                $result = $mapper($item);
                $channel->push(['index' => $index, 'result' => $result]);
            });
        }

        // Collect all results
        for ($i = 0; $i < count($items); $i++) {
            $item = $channel->pop();
            $results[$item['index']] = $item['result'];
        }

        // Reorder by original index
        ksort($results);
        return array_values($results);
    }
}
