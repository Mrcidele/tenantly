<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenancyBootstrapper;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RedisStore;

/**
 * Prefixa as chaves dos stores configurados com "tenant:{id}:". Código que
 * usa Cache::get('x') não precisa saber de tenant — e não consegue ler a
 * chave "x" de outro tenant. Vale também para rate limiter e locks.
 */
final readonly class CacheBootstrapper implements TenancyBootstrapper
{
    public function __construct(private CacheManager $cache) {}

    public function bootstrap(Tenant $tenant): void
    {
        foreach ($this->stores() as $name => $store) {
            $store->setPrefix($this->basePrefix($name).'tenant:'.$tenant->id.':');
        }
    }

    public function revert(): void
    {
        foreach ($this->stores() as $name => $store) {
            $store->setPrefix($this->basePrefix($name));
        }
    }

    private function basePrefix(string $store): string
    {
        $prefix = config("cache.stores.{$store}.prefix") ?? config('cache.prefix');

        return is_string($prefix) ? $prefix : '';
    }

    /**
     * @return array<string, RedisStore>
     */
    private function stores(): array
    {
        /** @var list<string> $names */
        $names = config('tenancy.cache.stores', []);
        $stores = [];

        foreach ($names as $name) {
            $store = $this->cache->store($name)->getStore();

            if ($store instanceof RedisStore) {
                $stores[$name] = $store;
            }
        }

        return $stores;
    }
}
