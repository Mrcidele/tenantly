<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\Database\UseMigratorConnectionForSchemaCommands;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        Event::listen(CommandStarting::class, [UseMigratorConnectionForSchemaCommands::class, 'starting']);
        Event::listen(CommandFinished::class, [UseMigratorConnectionForSchemaCommands::class, 'finished']);
    }
}
