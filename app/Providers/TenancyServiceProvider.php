<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\IdentifyTenant;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\Bootstrappers\CacheBootstrapper;
use App\Tenancy\Bootstrappers\FilesystemBootstrapper;
use App\Tenancy\Bootstrappers\ObservabilityBootstrapper;
use App\Tenancy\Bootstrappers\UrlBootstrapper;
use App\Tenancy\Database\ConnectionRouter;
use App\Tenancy\Database\DatabaseSessionSynchronizer;
use App\Tenancy\Database\TenantDatabaseStrategyResolver;
use App\Tenancy\Filesystem\TenantFilesystemDriver;
use App\Tenancy\Listeners\InvalidateResolutionCache;
use App\Tenancy\Listeners\SyncAuthenticatedUser;
use App\Tenancy\Listeners\TagOutgoingMail;
use App\Tenancy\Queue\TenantQueueContext;
use App\Tenancy\Resolution\TenantIdentifier;
use App\Tenancy\Resolution\TenantResolutionCache;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        $this->app->singleton(TenantQueueContext::class);

        foreach ([CacheBootstrapper::class, FilesystemBootstrapper::class, UrlBootstrapper::class, ObservabilityBootstrapper::class] as $bootstrapper) {
            $this->app->singleton($bootstrapper);
        }
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

        // Filas: tenant_id no payload de todo job e contexto restaurado ao processar.
        Queue::createPayloadUsing(fn (): array => $this->app->make(TenantQueueContext::class)->payload());
        Event::listen(JobProcessing::class, [TenantQueueContext::class, 'processing']);
        Event::listen([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class, JobReleasedAfterException::class], [TenantQueueContext::class, 'finished']);

        Storage::extend('tenant', fn (Application $app): Filesystem => $app->make(TenantFilesystemDriver::class)());

        Event::listen(MessageSending::class, TagOutgoingMail::class);

        Tenant::saved(static fn (Tenant $tenant) => app(InvalidateResolutionCache::class)->tenantChanged($tenant));
        Tenant::deleted(static fn (Tenant $tenant) => app(InvalidateResolutionCache::class)->tenantChanged($tenant));
        TenantDomain::saved(static fn (TenantDomain $domain) => app(InvalidateResolutionCache::class)->domainChanged($domain));
        TenantDomain::deleted(static fn (TenantDomain $domain) => app(InvalidateResolutionCache::class)->domainChanged($domain));
    }
}
