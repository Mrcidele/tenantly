<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\Tenant;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Token de uso único (60s) para levar um usuário autenticado para o host de
 * outro tenant. Necessário porque o cookie de sessão é restrito ao host:
 * a sessão de acme.tenantly.com não vale em globex.tenantly.com nem em
 * domínios customizados. Usado na troca de organização, no cadastro e na
 * impersonação.
 */
final readonly class TenantHandoff
{
    private const int TTL_SECONDS = 60;

    public function __construct(private CacheFactory $cache) {}

    /**
     * @param  array<string, string>  $extra
     */
    public function issue(string $userId, Tenant $tenant, array $extra = []): string
    {
        $token = Str::random(64);

        $this->store()->put($this->key($token), [
            'user_id' => $userId,
            'tenant_id' => $tenant->id,
            'extra' => $extra,
        ], self::TTL_SECONDS);

        return $tenant->url('/auth/handoff?token='.$token);
    }

    /**
     * @return array{user_id: string, extra: array<string, string>}|null
     */
    public function consume(string $token, Tenant $tenant): ?array
    {
        $payload = $this->store()->pull($this->key($token));

        if (! is_array($payload) || ($payload['tenant_id'] ?? null) !== $tenant->id || ! is_string($payload['user_id'] ?? null)) {
            return null;
        }

        /** @var array<string, string> $extra */
        $extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];

        return ['user_id' => $payload['user_id'], 'extra' => $extra];
    }

    private function key(string $token): string
    {
        return 'handoff:'.hash('sha256', $token);
    }

    private function store(): Repository
    {
        return $this->cache->store('central');
    }
}
