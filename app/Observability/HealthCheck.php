<?php

declare(strict_types=1);

namespace App\Observability;

use App\Tenancy\Database\DatabaseHealth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Redis\RedisManager;
use RuntimeException;

/** /up falha (500) se o Postgres ou o Redis não responderem: usado por healthcheck e balanceador. */
final readonly class HealthCheck
{
    public function __construct(private Container $container) {}

    public function handle(DiagnosingHealth $event): void
    {
        $this->container->make(DatabaseHealth::class)->ping();

        $pong = $this->container->make(RedisManager::class)->connection()->command('ping');

        if ($pong === false) {
            throw new RuntimeException('Redis indisponível.');
        }
    }
}
