<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\DatabaseManager;

/**
 * Comandos que alteram o schema rodam com o papel dono (`migrator`).
 * O papel da aplicação não tem permissão de DDL, então esquecer o
 * `--database` falharia de forma barulhenta; isto apenas torna o padrão correto.
 */
final class UseMigratorConnectionForSchemaCommands
{
    /** @var list<string> */
    private const array COMMANDS = [
        'migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset',
        'migrate:rollback', 'migrate:status', 'migrate:install',
        'db:seed', 'db:wipe', 'schema:dump',
    ];

    /** @var list<string|null> */
    private array $stack = [];

    public function __construct(private readonly DatabaseManager $db) {}

    public function starting(CommandStarting $event): void
    {
        if (! in_array($event->command, self::COMMANDS, true)) {
            return;
        }

        if ($event->input->hasParameterOption('--database')) {
            $this->stack[] = null;

            return;
        }

        $this->stack[] = $this->db->getDefaultConnection();
        $this->db->setDefaultConnection('migrator');
    }

    public function finished(CommandFinished $event): void
    {
        if (! in_array($event->command, self::COMMANDS, true) || $this->stack === []) {
            return;
        }

        $previous = array_pop($this->stack);

        if ($previous !== null) {
            $this->db->setDefaultConnection($previous);
        }
    }
}
