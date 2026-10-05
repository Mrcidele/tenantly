<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Descobre, pelo catálogo do Postgres, todas as tabelas com tenant_id e a
 * ordem de dependência entre elas (FKs). Usado por exportação, purga e
 * restauração: tabela nova entra automaticamente.
 */
final class TenantTables
{
    /**
     * Tabelas com tenant_id, ordenadas de pais para filhos.
     *
     * @return list<string>
     */
    public static function ordered(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $tables = self::all($db);

        /** @var list<object{child: string, parent: string}> $edges */
        $edges = $db->select(
            "select distinct tc.relname as child, tp.relname as parent
             from pg_constraint c
             join pg_class tc on tc.oid = c.conrelid
             join pg_class tp on tp.oid = c.confrelid
             join pg_namespace n on n.oid = tc.relnamespace
             where c.contype = 'f' and n.nspname = 'public' and tc.relname <> tp.relname"
        );

        $parents = array_fill_keys($tables, []);
        foreach ($edges as $edge) {
            if (isset($parents[$edge->child]) && in_array($edge->parent, $tables, true)) {
                $parents[$edge->child][] = $edge->parent;
            }
        }

        $ordered = [];
        $visit = static function (string $table) use (&$visit, &$ordered, $parents): void {
            if (in_array($table, $ordered, true)) {
                return;
            }
            foreach ($parents[$table] ?? [] as $parent) {
                $visit($parent);
            }
            $ordered[] = $table;
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    public static function all(ConnectionInterface $db): array
    {
        /** @var list<object{table_name: string}> $rows */
        $rows = $db->select(
            "select table_name from information_schema.columns
             where table_schema = 'public' and column_name = 'tenant_id' order by table_name"
        );

        return array_map(static fn (object $row): string => $row->table_name, $rows);
    }
}
