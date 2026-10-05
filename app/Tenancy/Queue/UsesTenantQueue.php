<?php

declare(strict_types=1);

namespace App\Tenancy\Queue;

use App\Tenancy\TenantContext;

/**
 * Jobs pesados de tenant chamam onTenantQueue() no construtor: tenants
 * marcados como grandes (tenants.queue, definido no painel) vão para uma
 * fila com workers próprios no Horizon.
 */
trait UsesTenantQueue
{
    protected function onTenantQueue(string $default = 'default'): void
    {
        $queue = app(TenantContext::class)->current()?->queue;

        /** @var list<string> $dedicated */
        $dedicated = config('tenancy.dedicated_queues', []);

        $this->onQueue(is_string($queue) && in_array($queue, $dedicated, true) ? $queue : $default);
    }
}
