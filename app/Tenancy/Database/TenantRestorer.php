<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Restaura UM tenant a partir de um backup restaurado em outro banco
 * (conexão de origem com papel dono/BYPASSRLS). A escrita usa a conexão da
 * aplicação com o contexto do tenant: o RLS impede, por construção, que a
 * restauração apague ou altere dados de qualquer outro tenant.
 */
final readonly class TenantRestorer
{
    public function __construct(private TenantContext $context) {}

    /**
     * @return array<string, int> linhas restauradas por tabela
     */
    public function restore(string $tenantId, string $sourceConnection): array
    {
        $source = DB::connection($sourceConnection);
        $tenantRow = $source->table('tenants')->where('id', $tenantId)->first();

        if ($tenantRow === null) {
            throw new RuntimeException("Tenant [{$tenantId}] não existe no backup.");
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            DB::table('tenants')->insert((array) $tenantRow);
            $tenant = Tenant::query()->findOrFail($tenantId);
        }
        $tables = TenantTables::ordered($sourceConnection);
        $result = [];

        $this->context->run($tenant, function () use ($source, $tenantId, $tables, &$result): void {
            DB::transaction(function () use ($source, $tenantId, $tables, &$result): void {
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete(); // RLS: só linhas deste tenant
                }

                $this->restoreUsers($source, $tenantId);

                foreach ($tables as $table) {
                    $rows = array_map(static fn (object $row): array => (array) $row, $source->table($table)->where('tenant_id', $tenantId)->get()->all());

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }

                    $result[$table] = count($rows);
                }
            });
        });

        return $result;
    }

    /** Usuários membros que não existem mais no banco atual (ex.: removidos após a purga). */
    private function restoreUsers(Connection $source, string $tenantId): void
    {
        /** @var list<string> $userIds */
        $userIds = $source->table('memberships')->where('tenant_id', $tenantId)->pluck('user_id')->all();

        /** @var list<string> $existing */
        $existing = $this->context->withoutTenancy('Restauração de tenant: checar usuários existentes', fn (): array => DB::connection('pgsql_admin')
            ->table('users')->whereIn('id', $userIds)->pluck('id')->all());

        $missing = $source->table('users')->whereIn('id', array_diff($userIds, $existing))->get()->all();

        foreach ($missing as $user) {
            DB::table('users')->insert((array) $user);
        }
    }
}
