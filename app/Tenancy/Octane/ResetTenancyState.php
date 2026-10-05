<?php

declare(strict_types=1);

namespace App\Tenancy\Octane;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;

/**
 * Listener do Octane (RequestReceived/RequestTerminated): reverte qualquer
 * estado global de tenant (prefixo de cache, disco, URL raiz, contexto de log)
 * deixado pela request anterior no mesmo worker. Complementa o middleware
 * ResetTenancy e o sincronizador de sessão do banco.
 */
final class ResetTenancyState
{
    public function handle(object $event): void
    {
        $app = property_exists($event, 'sandbox') ? $event->sandbox : null;
        $app = $app instanceof Container ? $app : app();

        $app->make(TenantContext::class)->reset();
    }
}
