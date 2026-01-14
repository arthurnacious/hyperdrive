<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

use Hyperdrive\Container\Container;

class DtoFactory
{
    public function __construct(
        private Container $container
    ) {}

    public function create(string $dtoClass, array $data): Dto
    {
        $reflection = new \ReflectionClass($dtoClass);

        // Get services needed for validate() method
        $services = $this->resolveValidateServices($reflection);

        // Create DTO instance
        return $reflection->newInstanceArgs(array_merge([$data], $services));
    }

    private function resolveValidateServices(\ReflectionClass $reflection): array
    {
        if (!$reflection->hasMethod('validate')) {
            return [];
        }

        $method = $reflection->getMethod('validate');
        $services = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $services[] = $this->container->get($type->getName());
            }
        }

        return $services;
    }
}
