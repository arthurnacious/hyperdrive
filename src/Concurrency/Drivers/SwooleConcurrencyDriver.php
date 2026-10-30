<?php

declare(strict_types=1);

namespace Hyperdrive\Concurrency\Drivers;

class SwooleConcurrencyDriver implements ConcurrencyDriverInterface
{
    public function any(array $operations): mixed
    {
        // Similar to OpenSwoole but using Swoole's coroutine API
        $wg = new \Swoole\Coroutine\WaitGroup();
        $channel = new \Swoole\Coroutine\Channel(1);
        $exceptions = [];

        foreach ($operations as $operation) {
            $wg->add();
            go(function () use ($operation, $wg, $channel, &$exceptions) {
                try {
                    $result = $operation();
                    $channel->push(['type' => 'success', 'value' => $result]);
                } catch (\Throwable $e) {
                    $exceptions[] = $e;

                    if (count($exceptions) === count($operations)) {
                        $channel->push(['type' => 'error', 'exceptions' => $exceptions]);
                    }
                } finally {
                    $wg->done();
                }
            });
        }

        $result = $channel->pop();
        $wg->wait();

        if ($result['type'] === 'success') {
            return $result['value'];
        }

        throw new \RuntimeException('All operations failed', 0, $result['exceptions'][0]);
    }

    public function race(array $operations): mixed
    {
        $channel = new \Swoole\Coroutine\Channel(1);

        foreach ($operations as $operation) {
            go(function () use ($operation, $channel) {
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

    public function map(array $items, callable $mapper): array
    {
        $results = [];
        $wg = new \Swoole\Coroutine\WaitGroup();

        foreach ($items as $index => $item) {
            $wg->add();
            go(function () use ($item, $mapper, $index, &$results, $wg) {
                $results[$index] = $mapper($item);
                $wg->done();
            });
        }

        $wg->wait();
        ksort($results);
        return array_values($results);
    }

    public function all(array $operations): array
    {
        if (\Swoole\Coroutine::getCid() > 0) {
            return $this->executeAllInCoroutine($operations);
        }

        // Coroutine\run() returns whether the scheduler ran, not the
        // callback's value, so the outcome is carried out by reference
        $results = [];
        $failure = null;
        \Swoole\Coroutine\run(function () use ($operations, &$results, &$failure) {
            try {
                $results = $this->executeAllInCoroutine($operations);
            } catch (\Throwable $e) {
                $failure = $e;
            }
        });

        if ($failure !== null) {
            throw $failure;
        }

        return $results;
    }

    private function executeAllInCoroutine(array $operations): array
    {
        $results = array_fill_keys(array_keys($operations), null);
        $failure = null;
        $group = new \Swoole\Coroutine\WaitGroup();

        foreach ($operations as $key => $operation) {
            $group->add();
            \Swoole\Coroutine::create(function () use ($key, $operation, &$results, &$failure, $group) {
                try {
                    $results[$key] = $operation();
                } catch (\Throwable $e) {
                    $failure ??= $e;
                } finally {
                    $group->done();
                }
            });
        }

        $group->wait();

        if ($failure !== null) {
            throw $failure;
        }

        return $results;
    }
}
