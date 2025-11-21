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
}
