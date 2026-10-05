<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTenantMember;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\RequireFeature;
use App\Http\Middleware\ResetTenancy;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

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

            $admin = config('tenancy.admin_domain');

            Route::middleware('web')
                ->domain(is_string($admin) ? $admin : 'admin.tenantly.localhost')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['api', 'tenant:header,subdomain,domain', 'throttle:tenant-api'])
                ->prefix('api')
                ->name('api.')
                ->group(base_path('routes/api.php'));

            // Qualquer outro host é tratado como tenant (subdomínio ou domínio customizado).
            Route::middleware(['web', 'tenant', 'throttle:tenant-web'])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'tenant', 'auth']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResetTenancy::class);
        $middleware->web(append: [EnsureTenantMember::class, HandleInertiaRequests::class]);
        $middleware->alias(['feature' => RequireFeature::class]);
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->getHost() === config('tenancy.admin_domain')
            ? route('admin.login')
            : '/login');
        $middleware->redirectUsersTo(fn (Request $request): string => $request->getHost() === config('tenancy.admin_domain') ? route('admin.dashboard') : '/');
        // Webhooks são autenticados pela assinatura do gateway.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // O tenant precisa estar identificado antes da sessão e do route model binding.
        $middleware->prependToPriorityList(before: StartSession::class, prepend: IdentifyTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
