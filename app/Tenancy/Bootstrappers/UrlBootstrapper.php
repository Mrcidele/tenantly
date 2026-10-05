<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenancyBootstrapper;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

/**
 * Fora de uma request do próprio tenant (filas, comandos, e-mails), as URLs
 * geradas por route()/url() apontam para o host do tenant.
 */
final readonly class UrlBootstrapper implements TenancyBootstrapper
{
    public function __construct(private Container $container) {}

    public function bootstrap(Tenant $tenant): void
    {
        $host = $this->container->bound('request') ? $this->container->make(Request::class)->getHost() : null;

        if ($host !== null && in_array($host, [$tenant->subdomainHost(), $tenant->primaryHost()], true)) {
            $this->url()->forceRootUrl(null);

            return;
        }

        $this->url()->forceRootUrl(rtrim($tenant->url('/'), '/'));
    }

    public function revert(): void
    {
        $this->url()->forceRootUrl(null);
    }

    private function url(): UrlGenerator
    {
        return $this->container->make(UrlGenerator::class);
    }
}
