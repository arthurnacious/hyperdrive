<?php

declare(strict_types=1);

namespace Hyperdrive\Exceptions;

class ModuleBoundaryException extends \Exception
{
    public static function notExported(
        string $consumingClass,
        string $consumingModule,
        string $dependencyClass,
        string $owningModule
    ): self {
        return new self(sprintf(
            '%s (registered in %s) depends on %s, which belongs to %s but is not in its exports. '
                . 'Add %s to %s\'s #[Module(exports: [...])], or drop the dependency.',
            $consumingClass,
            $consumingModule,
            $dependencyClass,
            $owningModule,
            $dependencyClass,
            $owningModule,
        ));
    }

    public static function notImported(
        string $consumingClass,
        string $consumingModule,
        string $dependencyClass,
        string $owningModule
    ): self {
        return new self(sprintf(
            '%s (registered in %s) depends on %s, which belongs to %s and is exported, '
                . 'but %s does not import %s. Add %s to %s\'s #[Module(imports: [...])].',
            $consumingClass,
            $consumingModule,
            $dependencyClass,
            $owningModule,
            $consumingModule,
            $owningModule,
            $owningModule,
            $consumingModule,
        ));
    }
}
