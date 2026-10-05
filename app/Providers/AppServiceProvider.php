<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\Database\UseMigratorConnectionForSchemaCommands;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UseMigratorConnectionForSchemaCommands::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Uma gate por permissão, resolvida pelo papel no tenant ativo.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, static fn (User $user): bool => $user->hasPermission($permission));
        }

        RateLimiter::for('signup', static fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->ip()));
        // Anti-enumeração de subdomínios.
        RateLimiter::for('subdomain-check', static fn (Request $request): Limit => Limit::perMinute(30)->by((string) $request->ip()));

        Event::listen(CommandStarting::class, [UseMigratorConnectionForSchemaCommands::class, 'starting']);
        Event::listen(CommandFinished::class, [UseMigratorConnectionForSchemaCommands::class, 'finished']);
    }
}
