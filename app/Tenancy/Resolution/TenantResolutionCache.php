<?php

declare(strict_types=1);

namespace App\Tenancy\Resolution;

use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Cache (Redis) de "chave de resolução → tenant". Usa um store central com
 * prefixo fixo, nunca o prefixo do tenant ativo. Guarda também resultados
 * negativos por pouco tempo para conter enumeração de subdomínios.
 */
final readonly class TenantResolutionCache
{
    private const string MISSING = '__missing__';

    public function __construct(private CacheFactory $cache) {}

    /**
     * @param  Closure(): ?Tenant  $resolve
     */
    public function remember(string $key, Closure $resolve): ?Tenant
    {
        $cached = $this->store()->get($this->key($key));

        if ($cached === self::MISSING) {
            return null;
        }

        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return (new Tenant)->newFromBuilder($cached);
        }

        $tenant = $resolve();

        if ($tenant === null) {
            $this->store()->put($this->key($key), self::MISSING, $this->ttl('negative_ttl', 30));

            return null;
        }

        $this->store()->put($this->key($key), $tenant->getAttributes(), $this->ttl('ttl', 300));
        $this->rememberKeyForTenant($tenant->id, $key);

        return $tenant;
    }

    /** Invalida todas as chaves que apontam para o tenant. */
    public function forgetTenant(string $tenantId): void
    {
        $index = $this->indexKey($tenantId);
        $keys = $this->store()->get($index, []);

        foreach (is_array($keys) ? $keys : [] as $key) {
            if (is_string($key)) {
                $this->store()->forget($this->key($key));
            }
        }

        $this->store()->forget($index);
    }

    public function forget(string $key): void
    {
        $this->store()->forget($this->key($key));
    }

    private function rememberKeyForTenant(string $tenantId, string $key): void
    {
        $index = $this->indexKey($tenantId);
        $keys = $this->store()->get($index, []);
        $keys = is_array($keys) ? $keys : [];

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->store()->forever($index, $keys);
        }
    }

    private function key(string $key): string
    {
        return 'tenancy:resolve:'.$key;
    }

    private function indexKey(string $tenantId): string
    {
        return 'tenancy:keys:'.$tenantId;
    }

    private function ttl(string $key, int $default): int
    {
        $ttl = config("tenancy.resolution_cache.{$key}");

        return is_int($ttl) ? $ttl : $default;
    }

    private function store(): Repository
    {
        $store = config('tenancy.resolution_cache.store');

        return $this->cache->store(is_string($store) ? $store : null);
    }
}
