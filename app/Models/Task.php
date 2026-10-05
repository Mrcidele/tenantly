<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit\Concerns\Auditable;
use App\Enums\TaskStatus;
use App\Tenancy\Concerns\BelongsToTenant;
use App\Tenancy\Contracts\StoresTenantData;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $title
 * @property TaskStatus $status
 * @property string|null $assignee_id
 * @property CarbonImmutable|null $due_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['project_id', 'title', 'status', 'assignee_id', 'due_on'])]
class Task extends Model implements StoresTenantData
{
    /** @use HasFactory<TaskFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'todo'];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
