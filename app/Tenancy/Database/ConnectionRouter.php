<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;

/**
 * Decide qual conexão cada model usa. Models sobrescrevem getConnectionName()
 * para consultar este router, então a decisão não depende de quem escreve a query.
 */
final readonly class ConnectionRouter
{
    public function __construct(
        private Container $container,
        private TenantDatabaseStrategyResolver $strategies,
    ) {}

    /** Banco central: tenants, usuários, memberships, assinaturas, auditoria. */
    public function central(): string
    {
        return $this->context()->isBypassed() ? $this->bypassConnection() : $this->centralConnection();
    }

    /** Dados de domínio: banco central ou dedicado, conforme a estratégia do tenant. */
    public function tenantData(): string
    {
        $context = $this->context();
        $tenant = $context->current();

        if ($context->isBypassed() || $tenant === null) {
            return $this->central();
        }

        return $this->strategies->for($tenant)->dataConnection($tenant);
    }

    public function centralConnection(): string
    {
        $name = config('tenancy.connections.central');

        return is_string($name) ? $name : 'pgsql';
    }

    public function bypassConnection(): string
    {
        $name = config('tenancy.connections.bypass');

        return is_string($name) ? $name : 'pgsql_admin';
    }

    private function context(): TenantContext
    {
        return $this->container->make(TenantContext::class);
    }
}
