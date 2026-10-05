<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\TransactionRolledBack;
use PDO;
use WeakMap;

/**
 * Mantém `app.tenant_id` e `app.user_id` da sessão do Postgres iguais ao
 * TenantContext. A checagem acontece antes de CADA query e compara com o
 * estado já aplicado em cada objeto PDO, então cobre conexões lazy,
 * reconexões, rollbacks e workers de longa duração sem depender da ordem de
 * eventos do ciclo de vida.
 */
final class DatabaseSessionSynchronizer
{
    /** @var WeakMap<PDO, string> */
    private WeakMap $applied;

    /** @var WeakMap<Connection, true> */
    private WeakMap $registered;

    public function __construct(private readonly Container $container)
    {
        $this->applied = new WeakMap;
        $this->registered = new WeakMap;
    }

    public function handleConnectionEstablished(ConnectionEstablished $event): void
    {
        $this->register($event->connection);
    }

    public function handleRollback(TransactionRolledBack $event): void
    {
        // Mudanças de set_config feitas dentro da transação foram desfeitas.
        $this->forget($event->connection);
    }

    public function register(Connection $connection): void
    {
        if (! $this->shouldSynchronize($connection) || isset($this->registered[$connection])) {
            return;
        }

        $this->registered[$connection] = true;

        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            $this->synchronize($connection);
        });
    }

    public function synchronize(Connection $connection): void
    {
        if ($connection->getRawPdo() === null) {
            $connection->reconnect();
        }

        $pdo = $connection->getPdo();
        $this->apply($pdo);

        if ($connection->getConfig('read') !== null && ($readPdo = $connection->getReadPdo()) !== $pdo) {
            $this->apply($readPdo);
        }
    }

    public function forget(Connection $connection): void
    {
        foreach ([$connection->getRawPdo(), $connection->getRawReadPdo()] as $pdo) {
            if ($pdo instanceof PDO) {
                unset($this->applied[$pdo]);
            }
        }
    }

    private function apply(PDO $pdo): void
    {
        $context = $this->container->make(TenantContext::class);
        $tenantId = $context->id() ?? '';
        $userId = $context->userId() ?? '';
        $state = $tenantId.'|'.$userId;

        if (($this->applied[$pdo] ?? null) === $state) {
            return;
        }

        $statement = $pdo->prepare("select set_config('app.tenant_id', ?, false), set_config('app.user_id', ?, false)");
        $statement->execute([$tenantId, $userId]);

        $this->applied[$pdo] = $state;
    }

    private function shouldSynchronize(Connection $connection): bool
    {
        /** @var list<string> $skip */
        $skip = config('tenancy.connections.unsynchronized', []);

        return $connection->getDriverName() === 'pgsql'
            && ! in_array($connection->getName(), $skip, true);
    }
}
