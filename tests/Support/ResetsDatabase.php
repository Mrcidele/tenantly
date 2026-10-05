<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/**
 * Banco limpo por TRUNCATE (como migrator) em vez de transação por teste.
 *
 * Transações escondem dados entre conexões; aqui a conexão da aplicação
 * (sujeita ao RLS) e a conexão com BYPASSRLS de withoutTenancy() enxergam o
 * mesmo estado commitado — exatamente como em produção.
 */
trait ResetsDatabase
{
    /** @var list<string>|null */
    private static ?array $tablesToReset = null;

    protected function setUpResetsDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', ['--database' => 'migrator', '--drop-views' => true]);
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
            self::$tablesToReset = null;
        }

        $migrator = DB::connection('migrator');
        $tables = self::$tablesToReset ??= $this->resettableTables($migrator);

        if ($tables !== []) {
            $migrator->statement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
        }

        $migrator->disconnect();
    }

    protected function tearDownResetsDatabase(): void
    {
        foreach (DB::getConnections() as $connection) {
            $connection->disconnect();
        }
    }

    /**
     * @return list<string>
     */
    private function resettableTables(Connection $connection): array
    {
        $rows = $connection->select(
            "select tablename from pg_tables where schemaname = 'public' and tablename <> 'migrations' order by tablename"
        );

        return array_map(static fn (object $row): string => '"'.$row->tablename.'"', $rows);
    }
}
