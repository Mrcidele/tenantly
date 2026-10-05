<?php

declare(strict_types=1);

namespace App\Providers;

use App\Billing\StartTrialOnProvisioning;
use App\Domains\DnsResolver;
use App\Domains\NativeDnsResolver;
use App\Entitlements\Entitlements;
use App\Enums\Permission;
use App\Events\TenantProvisioningBilling;
use App\Livewire\Pulse\TenantUsage;
use App\Models\PersonalAccessToken;
use App\Models\Tenant;
use App\Models\User;
use App\Observability\HealthCheck;
use App\Tenancy\Database\UseMigratorConnectionForSchemaCommands;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UseMigratorConnectionForSchemaCommands::class);
        $this->app->scoped(Entitlements::class);
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Uma gate por permissão, resolvida pelo papel no tenant ativo.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, static fn (User $user): bool => $user->hasPermission($permission));
        }

        // Com tenant ativo o cache (e portanto o rate limiter) já é prefixado por tenant.
        RateLimiter::for('tenant-web', static fn (Request $request): array => [
            Limit::perMinute(self::rate('web_per_tenant'))->by('tenant'),
            Limit::perMinute(self::rate('web_per_ip'))->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('tenant-api', static fn (Request $request): array => [
            Limit::perMinute(self::rate('api_per_tenant'))->by('tenant'),
            Limit::perMinute(self::rate('api_per_token'))->by('token:'.hash('sha256', (string) $request->bearerToken()).':'.$request->ip()),
        ]);
        RateLimiter::for('signup', static fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->ip()));
        // Anti-enumeração de subdomínios.
        RateLimiter::for('subdomain-check', static fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()));

        Event::listen(TenantProvisioningBilling::class, StartTrialOnProvisioning::class);
        Event::listen(DiagnosingHealth::class, HealthCheck::class);

        // Horizon e Pulse: só a equipe interna (guard admin), no domínio do painel.
        Horizon::auth(static fn (Request $request): bool => $request->user('admin') !== null);
        Gate::define('viewPulse', static fn (?Authenticatable $user = null): bool => auth('admin')->check());
        Livewire::component('pulse.tenant-usage', TenantUsage::class);

        // Feature flags (Pennant) escopadas ao tenant ativo.
        Feature::resolveScopeUsing(static fn (): ?Tenant => app(TenantContext::class)->current());
        Feature::define('new-dashboard', static fn (Tenant $tenant): bool => false);
        Feature::define('beta-reports', static fn (Tenant $tenant): bool => crc32($tenant->id) % 10 === 0);

        Event::listen(CommandStarting::class, [UseMigratorConnectionForSchemaCommands::class, 'starting']);
        Event::listen(CommandFinished::class, [UseMigratorConnectionForSchemaCommands::class, 'finished']);
    }

    private static function rate(string $key): int
    {
        $value = config('tenancy.rate_limits.'.$key);

        return is_int($value) ? $value : 600;
    }
}
