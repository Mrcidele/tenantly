<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Exceptions\TenantInactive;
use App\Tenancy\Exceptions\TenantNotFound;
use App\Tenancy\Resolution\TenantIdentifier;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: `->middleware('tenant')` (subdomínio + domínio customizado) ou
 * `->middleware('tenant:header,subdomain')` para a API.
 */
final readonly class IdentifyTenant
{
    public function __construct(
        private TenantIdentifier $identifier,
        private TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$strategies): Response
    {
        $tenant = $this->identifier->identify($request, $strategies !== [] ? array_values($strategies) : ['subdomain', 'domain']);

        if ($tenant === null) {
            throw new TenantNotFound;
        }

        if (! $tenant->isActive()) {
            throw new TenantInactive($tenant);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
