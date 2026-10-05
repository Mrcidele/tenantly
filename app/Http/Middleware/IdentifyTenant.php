<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\Exceptions\TenantInactive;
use App\Tenancy\Exceptions\TenantNotFound;
use App\Tenancy\Resolution\TenantIdentifier;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
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
        private CacheFactory $cache,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$strategies): Response
    {
        $tenant = $this->identifier->identify($request, $strategies !== [] ? array_values($strategies) : ['subdomain', 'domain']);

        if ($tenant === null) {
            $this->throttleUnknownHosts($request);

            throw new TenantNotFound;
        }

        if (! $tenant->isActive()) {
            throw new TenantInactive($tenant);
        }

        $this->context->set($tenant);

        return $next($request);
    }

    /** Anti-enumeração: muitos hosts inexistentes a partir do mesmo IP recebem 429. */
    private function throttleUnknownHosts(Request $request): void
    {
        $store = $this->cache->store('central');
        $key = 'unknown-host:'.$request->ip().':'.now()->format('YmdHi');
        $hits = $store->increment($key);
        $store->put($key, $hits, 120);

        $max = config('tenancy.unknown_host_limit_per_minute');

        if (is_int($hits) && $hits > (is_int($max) ? $max : 20)) {
            abort(429);
        }
    }
}
