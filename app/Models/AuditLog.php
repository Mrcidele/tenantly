<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string|null $impersonator_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'actor_type', 'actor_id', 'impersonator_id', 'action', 'subject_type', 'subject_id', 'properties', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    public const null UPDATED_AT = null;

    public static function allowsCentralRecords(): bool
    {
        return true;
    }

    protected static function booted(): void
    {
        // Trilha de auditoria é somente-inserção.
        static::updating(static fn (): bool => false);
        static::deleting(static fn (): bool => false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
