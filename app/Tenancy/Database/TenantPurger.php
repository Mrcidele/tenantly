<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Exclusão definitiva (LGPD) após o prazo de retenção: dados de domínio em
 * banco dedicado, linhas do tenant (FK em cascata), usuários que ficaram sem
 * nenhuma organização e o próprio tenant.
 */
final readonly class TenantPurger
{
    public function __construct(
        private TenantContext $context,
        private TenantDatabaseStrategyResolver $strategies,
    ) {}

    /** @return int usuários órfãos removidos */
    public function purge(Tenant $tenant): int
    {
        $this->context->run($tenant, function (Tenant $tenant): void {
            $connection = $this->strategies->for($tenant)->dataConnection($tenant);

            if ($connection !== config('tenancy.connections.central')) {
                foreach (array_reverse(TenantTables::ordered($connection)) as $table) {
                    DB::connection($connection)->table($table)->delete();
                }
            }
        });

        return $this->context->withoutTenancy("Purga LGPD do tenant {$tenant->id}", function () use ($tenant): int {
            $members = DB::connection('pgsql_admin')->table('memberships')->where('tenant_id', $tenant->id)->pluck('user_id')->all();

            // Cascata (FK) remove memberships, projetos, faturas, auditoria etc.
            DB::connection('pgsql_admin')->table('tenants')->where('id', $tenant->id)->delete();

            return DB::connection('pgsql_admin')->table('users')
                ->whereIn('id', $members)
                ->whereNotExists(static fn (Builder $q): Builder => $q->selectRaw('1')->from('memberships')->whereColumn('memberships.user_id', 'users.id'))
                ->delete();
        });
    }
}
