<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\Database\ConnectionRouter;
use App\Tenancy\Database\DatabaseSessionSynchronizer;
use App\Tenancy\Database\TenantDatabaseStrategyResolver;
use App\Tenancy\Listeners\InvalidateResolutionCache;
use App\Tenancy\Listeners\SyncAuthenticatedUser;
use App\Tenancy\Resolution\TenantIdentifier;
use App\Tenancy\Resolution\TenantResolutionCache;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Events\TokenAuthenticated;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Um por request/job: Octane e filas descartam a instância ao final.
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(DatabaseSessionSynchronizer::class);
        $this->app->singleton(ConnectionRouter::class);
        $this->app->singleton(TenantDatabaseStrategyResolver::class);
        $this->app->singleton(TenantIdentifier::class);
        $this->app->singleton(TenantResolutionCache::class);
    }

    public function boot(Router $router, DatabaseManager $db, DatabaseSessionSynchronizer $synchronizer): void
    {
        $this->loadMigrationsFrom(database_path('migrations/tenant'));

        $router->aliasMiddleware('tenant', IdentifyTenant::class);

        Event::listen(ConnectionEstablished::class, [DatabaseSessionSynchronizer::class, 'handleConnectionEstablished']);
        Event::listen(TransactionRolledBack::class, [DatabaseSessionSynchronizer::class, 'handleRollback']);

        // Conexões resolvidas antes deste provider.
        foreach ($db->getConnections() as $connection) {
            $synchronizer->register($connection);
        }

        Event::listen([Authenticated::class, Login::class, Logout::class, TokenAuthenticated::class], SyncAuthenticatedUser::class);

        Tenant::saved(static fn (Tenant $tenant) => app(InvalidateResolutionCache::class)->tenantChanged($tenant));
        Tenant::deleted(static fn (Tenant $tenant) => app(InvalidateResolutionCache::class)->tenantChanged($tenant));
        TenantDomain::saved(static fn (TenantDomain $domain) => app(InvalidateResolutionCache::class)->domainChanged($domain));
        TenantDomain::deleted(static fn (TenantDomain $domain) => app(InvalidateResolutionCache::class)->domainChanged($domain));
    }
}
