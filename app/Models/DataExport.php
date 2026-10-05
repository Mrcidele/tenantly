<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\DataExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $requested_by
 * @property string $status
 * @property string|null $path
 * @property int|null $size_bytes
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['requested_by', 'status', 'path', 'size_bytes', 'completed_at', 'expires_at'])]
class DataExport extends Model
{
    /** @use HasFactory<DataExportFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    public const int RETENTION_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'size_bytes' => 'integer',
        ];
    }
}
