<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DatabaseStrategy;
use App\Enums\TenantStatus;
use App\Tenancy\Concerns\UsesCentralConnection;
use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property DatabaseStrategy $database_strategy
 * @property array<string, string>|null $database_config
 * @property array<string, mixed>|null $branding
 * @property array<string, mixed>|null $settings
 * @property array<string, int|bool>|null $limit_overrides
 * @property string|null $primary_domain
 * @property string|null $queue
 * @property array<string, mixed>|null $provisioning
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $deletion_requested_at
 * @property CarbonImmutable|null $purge_after
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'status', 'branding', 'settings'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids, UsesCentralConnection;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'provisioning',
        'database_strategy' => 'shared',
    ];

    /**
     * @return HasMany<TenantDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function subdomainHost(): string
    {
        return $this->slug.'.'.self::centralDomain();
    }

    /** Host canônico: domínio customizado primário verificado, senão o subdomínio. */
    public function primaryHost(): string
    {
        $domain = $this->primary_domain;

        return is_string($domain) && $domain !== '' ? $domain : $this->subdomainHost();
    }

    public function url(string $path = '/'): string
    {
        $appUrl = config('app.url');
        $appUrl = is_string($appUrl) ? $appUrl : 'https://localhost';
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$this->primaryHost().($port !== null && $port !== false ? ':'.$port : '').'/'.ltrim($path, '/');
    }

    public static function centralDomain(): string
    {
        $domain = config('tenancy.central_domain');

        return is_string($domain) ? $domain : 'tenantly.localhost';
    }

    public function isActive(): bool
    {
        return $this->status->isAccessible();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'database_strategy' => DatabaseStrategy::class,
            'database_config' => 'array',
            'branding' => 'array',
            'settings' => 'array',
            'limit_overrides' => 'array',
            'provisioning' => 'array',
            'suspended_at' => 'immutable_datetime',
            'deletion_requested_at' => 'immutable_datetime',
            'purge_after' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
