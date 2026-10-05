<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Tests\Support\ResetsDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(ResetsDatabase::class)
    ->in('Feature', 'Isolation');

pest()->extend(TestCase::class)->in('Unit', 'Arch');

function tenancy(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * @template T
 *
 * @param  callable(Tenant): T  $callback
 * @return T
 */
function inTenant(Tenant $tenant, callable $callback): mixed
{
    return tenancy()->run($tenant, $callback);
}

function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return 'http://'.$tenant->subdomainHost().'/'.ltrim($path, '/');
}

function centralUrl(string $path = '/'): string
{
    return 'http://'.Tenant::centralDomain().'/'.ltrim($path, '/');
}
