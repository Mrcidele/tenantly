<?php

declare(strict_types=1);

namespace App\Tenancy\Resolution;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\Concerns\TenantScope;
use App\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** app.cliente.com → tenant dono do domínio verificado. */
final readonly class CustomDomainResolver implements TenantResolver
{
    public function __construct(private TenantResolutionCache $cache) {}

    public function resolve(Request $request): ?Tenant
    {
        $host = Str::lower($request->getHost());

        if ($host === Str::lower(Tenant::centralDomain()) || str_ends_with($host, '.'.Str::lower(Tenant::centralDomain()))) {
            return null;
        }

        return $this->cache->remember('domain:'.$host, static function () use ($host): ?Tenant {
            // Lookup pré-contexto: o RLS (política public_lookup) só expõe domínios verificados.
            $tenantId = TenantDomain::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('domain', $host)
                ->whereNotNull('verified_at')
                ->value('tenant_id');

            return is_string($tenantId) ? Tenant::query()->find($tenantId) : null;
        });
    }
}
