<?php

declare(strict_types=1);

namespace App\Tenancy\Resolution;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API: `X-Tenant: <uuid|slug>`. Não concede acesso por si só — o token
 * Sanctum é buscado sob RLS do tenant resolvido, então um header apontando
 * para outro tenant simplesmente não autentica.
 */
final readonly class HeaderResolver implements TenantResolver
{
    public function __construct(private TenantResolutionCache $cache) {}

    public function resolve(Request $request): ?Tenant
    {
        $header = config('tenancy.header');
        $value = $request->headers->get(is_string($header) ? $header : 'X-Tenant');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = Str::lower(trim($value));

        if (Str::isUuid($value)) {
            return $this->cache->remember('id:'.$value, static fn (): ?Tenant => Tenant::query()->find($value));
        }

        return $this->cache->remember('slug:'.$value, static fn (): ?Tenant => Tenant::query()->where('slug', $value)->first());
    }
}
