<?php

declare(strict_types=1);

namespace App\Tenancy\Listeners;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\Resolution\TenantResolutionCache;

final readonly class InvalidateResolutionCache
{
    public function __construct(private TenantResolutionCache $cache) {}

    public function tenantChanged(Tenant $tenant): void
    {
        $this->cache->forgetTenant($tenant->id);
        $this->cache->forget('slug:'.$tenant->slug);
        $this->cache->forget('id:'.$tenant->id);

        $original = $tenant->getOriginal('slug');

        if (is_string($original)) {
            $this->cache->forget('slug:'.$original);
        }
    }

    public function domainChanged(TenantDomain $domain): void
    {
        $this->cache->forget('domain:'.$domain->domain);
        $this->cache->forgetTenant($domain->tenant_id);
    }
}
