<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenancy\Database\TenantRestorer;
use Illuminate\Console\Command;

/**
 * Restaura um único tenant a partir de um backup já restaurado em outro banco:
 *   docker/backup/restore.sh backup.dump tenantly_restore
 *   php artisan tenants:restore <tenant-id> --source=restore_source
 */
final class RestoreTenant extends Command
{
    protected $signature = 'tenants:restore {tenant : ID do tenant} {--source=restore_source : Conexão apontando para o banco restaurado} {--force}';

    protected $description = 'Restaura os dados de um único tenant a partir de um backup';

    public function handle(TenantRestorer $restorer): int
    {
        $tenant = (string) $this->argument('tenant');
        $source = (string) $this->option('source');

        if (! $this->option('force') && ! $this->confirm("Substituir TODOS os dados atuais do tenant {$tenant} pelos do backup?")) {
            return self::FAILURE;
        }

        foreach ($restorer->restore($tenant, $source) as $table => $count) {
            $this->line(sprintf('%-28s %d', $table, $count));
        }

        $this->info('Tenant restaurado.');

        return self::SUCCESS;
    }
}
