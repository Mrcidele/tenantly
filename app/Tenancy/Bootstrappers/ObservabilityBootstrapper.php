<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenancyBootstrapper;
use Illuminate\Contracts\Container\Container;
use Illuminate\Log\Context\Repository as LogContext;
use Sentry\State\Scope;

use function Sentry\configureScope;

/**
 * tenant_id em todo log (Laravel Context entra automaticamente nos logs e é
 * propagado para jobs) e como tag nos eventos do Sentry.
 */
final readonly class ObservabilityBootstrapper implements TenancyBootstrapper
{
    public function __construct(private Container $container) {}

    public function bootstrap(Tenant $tenant): void
    {
        $context = $this->container->make(LogContext::class);
        $context->add('tenant_id', $tenant->id);
        $context->add('tenant_slug', $tenant->slug);

        configureScope(static function (Scope $scope) use ($tenant): void {
            $scope->setTag('tenant_id', $tenant->id);
            $scope->setTag('tenant_slug', $tenant->slug);
        });
    }

    public function revert(): void
    {
        $context = $this->container->make(LogContext::class);
        $context->forget('tenant_id');
        $context->forget('tenant_slug');

        configureScope(static function (Scope $scope): void {
            $scope->removeTag('tenant_id');
            $scope->removeTag('tenant_slug');
        });
    }
}
