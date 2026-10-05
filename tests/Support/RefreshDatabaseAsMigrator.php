<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * As migrations rodam com o papel dono do schema; os testes rodam (dentro de
 * uma transação) com o papel da aplicação, sujeito ao RLS — igual à produção.
 */
trait RefreshDatabaseAsMigrator
{
    use RefreshDatabase {
        migrateFreshUsing as baseMigrateFreshUsing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return [...$this->baseMigrateFreshUsing(), '--database' => 'migrator'];
    }
}
