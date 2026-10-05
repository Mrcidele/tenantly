<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Enums\Limit;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\RedisManager;

/**
 * Contadores de uso no Redis (atômicos e baratos por request). As chaves
 * incluem o tenant explicitamente; PersistUsageCounters grava snapshots no banco.
 */
final readonly class UsageMeter
{
    public function __construct(private RedisManager $redis) {}

    public function increment(string $tenantId, Limit $metric, int $by = 1): int
    {
        $key = $this->key($tenantId, $metric);
        $result = $this->connection()->command('incrby', [$key, $by]);
        $value = is_numeric($result) ? (int) $result : 0;

        if ($metric === Limit::ApiCallsPerMonth && $value === $by) {
            $this->connection()->command('expire', [$key, 60 * 60 * 24 * 40]);
        }

        return $value;
    }

    public function get(string $tenantId, Limit $metric): int
    {
        $value = $this->connection()->command('get', [$this->key($tenantId, $metric)]);

        return is_numeric($value) ? (int) $value : 0;
    }

    public function set(string $tenantId, Limit $metric, int $value): void
    {
        $this->connection()->command('set', [$this->key($tenantId, $metric), $value]);
    }

    /**
     * @return list<array{tenant_id: string, metric: Limit, period: string, value: int}>
     */
    public function all(): array
    {
        $prefix = config('database.redis.options.prefix');
        $prefix = is_string($prefix) ? $prefix : '';
        $result = [];

        foreach ((array) $this->connection()->command('keys', ['usage:*']) as $key) {
            $key = is_string($key) ? str_replace($prefix, '', $key) : '';
            $parts = explode(':', $key);

            if (count($parts) !== 4 || ($metric = Limit::tryFrom($parts[2])) === null) {
                continue;
            }

            $result[] = ['tenant_id' => $parts[1], 'metric' => $metric, 'period' => $parts[3], 'value' => $this->get($parts[1], $metric)];
        }

        return $result;
    }

    public static function period(Limit $metric): string
    {
        return $metric === Limit::ApiCallsPerMonth ? now()->format('Y-m') : 'total';
    }

    private function key(string $tenantId, Limit $metric): string
    {
        return 'usage:'.$tenantId.':'.$metric->value.':'.self::period($metric);
    }

    private function connection(): Connection
    {
        return $this->redis->connection('default');
    }
}
