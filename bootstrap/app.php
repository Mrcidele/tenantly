<?php

declare(strict_types=1);

use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\ResetTenancy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $central = config('tenancy.central_domain');

            // Rotas centrais vêm primeiro: casam apenas no domínio central.
            Route::middleware('web')
                ->domain(is_string($central) ? $central : 'tenantly.localhost')
                ->name('central.')
                ->group(base_path('routes/central.php'));

            Route::middleware(['api', 'tenant:header,subdomain,domain'])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));

            // Qualquer outro host é tratado como tenant (subdomínio ou domínio customizado).
            Route::middleware(['web', 'tenant'])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResetTenancy::class);

        // O tenant precisa estar identificado antes da sessão e do route model binding.
        $middleware->prependToPriorityList(before: StartSession::class, prepend: IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
