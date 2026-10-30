<?php

declare(strict_types=1);

namespace Hyperdrive\View;

/**
 * Turns a view name + data into HTML for #[Render] routes. Hyperdrive
 * ships no engine of its own - the application binds one in a module's
 * injectables, e.g. [ViewEngineInterface::class => MyEngine::class].
 *
 * Implementations must be stateless between renders - under
 * OpenSwoole/Swoole a single instance serves every request a worker handles.
 */
interface ViewEngineInterface
{
    public function render(string $view, array $data = []): string;
}
