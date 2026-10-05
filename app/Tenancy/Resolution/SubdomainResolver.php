<?php

declare(strict_types=1);

namespace App\Tenancy\Resolution;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** acme.tenantly.com → tenant com slug "acme". */
final readonly class SubdomainResolver implements TenantResolver
{
    public function __construct(private TenantResolutionCache $cache) {}

    public function resolve(Request $request): ?Tenant
    {
        $slug = self::slugFromHost($request->getHost());

        if ($slug === null) {
            return null;
        }

        return $this->cache->remember('slug:'.$slug, static fn (): ?Tenant => Tenant::query()->where('slug', $slug)->first());
    }

    public static function slugFromHost(string $host): ?string
    {
        $host = Str::lower($host);
        $suffix = '.'.Str::lower(Tenant::centralDomain());

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        return $slug !== '' && ! str_contains($slug, '.') ? $slug : null;
    }
}
