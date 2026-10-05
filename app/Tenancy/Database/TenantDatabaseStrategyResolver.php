<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Enums\DatabaseStrategy;
use App\Models\Tenant;
use App\Tenancy\Contracts\TenantDatabaseStrategy;
use Illuminate\Contracts\Container\Container;

final readonly class TenantDatabaseStrategyResolver
{
    public function __construct(private Container $container) {}

    public function for(Tenant $tenant): TenantDatabaseStrategy
    {
        return match ($tenant->database_strategy) {
            DatabaseStrategy::Shared => $this->container->make(SharedDatabaseStrategy::class),
            DatabaseStrategy::Dedicated => $this->container->make(DedicatedDatabaseStrategy::class),
        };
    }
}
