<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\Database\UseMigratorConnectionForSchemaCommands;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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

        Event::listen(CommandStarting::class, [UseMigratorConnectionForSchemaCommands::class, 'starting']);
        Event::listen(CommandFinished::class, [UseMigratorConnectionForSchemaCommands::class, 'finished']);
    }
}
