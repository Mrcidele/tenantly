<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenantDatabaseStrategy;

/** Banco único com isolamento por tenant_id + Row Level Security. */
final class SharedDatabaseStrategy implements TenantDatabaseStrategy
{
    public function dataConnection(Tenant $tenant): string
    {
        $name = config('tenancy.connections.central');

        return is_string($name) ? $name : 'pgsql';
    }

    public function provision(Tenant $tenant): void
    {
        // As tabelas já existem no banco central; nada a fazer.
    }
}
