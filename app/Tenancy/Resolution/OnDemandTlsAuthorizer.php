<?php

declare(strict_types=1);

namespace App\Tenancy\Resolution;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\Concerns\TenantScope;
use Illuminate\Support\Str;

/**
 * Responde ao "ask" do Caddy (on-demand TLS): só emite certificado para
 * subdomínios de tenants existentes e domínios customizados verificados,
 * evitando que qualquer host apontado para nós consuma cota da CA.
 */
final class OnDemandTlsAuthorizer
{
    public function allows(string $host): bool
    {
        $host = Str::lower(trim($host));
        $central = Str::lower(Tenant::centralDomain());

        $admin = config('tenancy.admin_domain');

        if ($host === $central || (is_string($admin) && $host === Str::lower($admin))) {
            return true;
        }

        $slug = SubdomainResolver::slugFromHost($host);

        if ($slug !== null) {
            return Tenant::query()->where('slug', $slug)->exists();
        }

        return TenantDomain::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('domain', $host)
            ->whereNotNull('verified_at')
            ->exists();
    }
}
