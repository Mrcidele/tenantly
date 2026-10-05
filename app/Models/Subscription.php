<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status só muda via App\Billing\SubscriptionStateMachine.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $plan_id
 * @property SubscriptionStatus $status
 * @property string $gateway
 * @property string|null $gateway_subscription_id
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $current_period_starts_at
 * @property CarbonImmutable|null $current_period_ends_at
 * @property CarbonImmutable|null $grace_ends_at
 * @property CarbonImmutable|null $suspended_at
 * @property CarbonImmutable|null $canceled_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Plan $plan
 */
#[Fillable(['plan_id', 'gateway', 'gateway_subscription_id', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'ends_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'immutable_datetime',
            'current_period_starts_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'suspended_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
