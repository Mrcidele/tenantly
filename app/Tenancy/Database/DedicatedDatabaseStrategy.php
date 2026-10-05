<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenantDatabaseStrategy;
use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

/**
 * Dados de domínio em um banco dedicado (clientes enterprise). As conexões
 * (`connection` com o papel da aplicação e `migrator_connection` com o papel
 * dono) são definidas pela operação em config/database.php; o tenant guarda
 * apenas os nomes. O banco dedicado também tem RLS: a defesa em camadas se mantém.
 */
final readonly class DedicatedDatabaseStrategy implements TenantDatabaseStrategy
{
    public function __construct(private Kernel $artisan) {}

    public function dataConnection(Tenant $tenant): string
    {
        return $this->connectionName($tenant, 'connection');
    }

    public function provision(Tenant $tenant): void
    {
        $this->artisan->call('migrate', [
            '--database' => $this->connectionName($tenant, 'migrator_connection'),
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    private function connectionName(Tenant $tenant, string $key): string
    {
        $name = $tenant->database_config[$key] ?? null;

        if (! is_string($name) || config("database.connections.{$name}") === null) {
            throw new RuntimeException("Tenant [{$tenant->id}] sem conexão dedicada [{$key}] configurada.");
        }

        return $name;
    }
}
