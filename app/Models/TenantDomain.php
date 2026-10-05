<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DomainStatus;
use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\TenantDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $domain
 * @property bool $is_primary
 * @property DomainStatus $status
 * @property string $verification_token
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $last_checked_at
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['domain', 'is_primary'])]
class TenantDomain extends Model
{
    /** @use HasFactory<TenantDomainFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'is_primary' => false,
    ];

    protected static function booted(): void
    {
        static::creating(static function (TenantDomain $domain): void {
            $domain->domain = Str::lower(trim($domain->domain));
            $token = $domain->getAttribute('verification_token');
            $domain->verification_token = is_string($token) ? $token : Str::random(40);
        });
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** Registro TXT que comprova a posse do domínio. */
    public function verificationRecordName(): string
    {
        return '_tenantly.'.$this->domain;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'is_primary' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
