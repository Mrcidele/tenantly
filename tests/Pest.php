<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Composer\Autoload\ClassLoader;
use Tests\Support\ResetsDatabase;
use Tests\TestCase;

// O laravel/pint registra o próprio código sob o prefixo PSR-4 "App\", o que
// faria o Pest Arch analisar classes do Pint como se fossem da aplicação.
foreach (ClassLoader::getRegisteredLoaders() as $loader) {
    $loader->setPsr4('App\\', [dirname(__DIR__).'/app']);
}

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
