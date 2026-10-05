<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers de migration para tabelas de tenant. O teste de catálogo
 * (tests/Isolation/RowLevelSecurityCatalogTest) falha se alguma tabela com
 * `tenant_id` não tiver RLS forçado — então esquecer este helper quebra o CI.
 */
final class TenantSchema
{
    public static function tenantColumn(Blueprint $table, bool $nullable = false): void
    {
        $table->uuid('tenant_id')->nullable($nullable);

        // Em bancos dedicados não existe a tabela `tenants`.
        if (Schema::hasTable('tenants')) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        }

        $table->index('tenant_id');
    }

    /**
     * ENABLE + FORCE RLS e política `tenant_isolation`. Com $allowCentralInserts,
     * linhas com tenant_id NULL podem ser inseridas (mas só são lidas com BYPASSRLS).
     */
    public static function enableRowLevelSecurity(string $table, bool $allowCentralInserts = false): void
    {
        $quoted = self::quote($table);

        DB::statement("ALTER TABLE {$quoted} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$quoted} FORCE ROW LEVEL SECURITY");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$quoted} ".
            'USING (tenant_id = current_tenant_id()) '.
            'WITH CHECK (tenant_id = current_tenant_id())'
        );

        if ($allowCentralInserts) {
            DB::statement("CREATE POLICY central_insert ON {$quoted} FOR INSERT WITH CHECK (tenant_id IS NULL)");
        }
    }

    public static function disableRowLevelSecurity(string $table): void
    {
        $quoted = self::quote($table);

        DB::statement("DROP POLICY IF EXISTS central_insert ON {$quoted}");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$quoted}");
        DB::statement("ALTER TABLE {$quoted} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$quoted} DISABLE ROW LEVEL SECURITY");
    }

    private static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
