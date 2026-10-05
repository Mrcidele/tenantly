<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingInterval;
use App\Tenancy\Concerns\UsesCentralConnection;
use Carbon\CarbonImmutable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property int $price_cents
 * @property string $currency
 * @property BillingInterval $interval
 * @property int $trial_days
 * @property bool $is_active
 * @property int $sort
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PlanFeature> $features
 */
#[Fillable(['code', 'name', 'price_cents', 'currency', 'interval', 'trial_days', 'is_active', 'sort'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUuids, UsesCentralConnection;

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * @return array<string, string>
     */
    public function featureMap(): array
    {
        /** @var array<string, string> */
        return $this->features->mapWithKeys(static fn (PlanFeature $feature): array => [$feature->feature => $feature->value])->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval' => BillingInterval::class,
            'is_active' => 'boolean',
            'price_cents' => 'integer',
            'trial_days' => 'integer',
            'sort' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
