<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<model-property<Subscription>, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'gateway' => 'fake',
            'current_period_starts_at' => now()->startOfDay(),
            'current_period_ends_at' => now()->startOfDay()->addMonth(),
        ];
    }

    public function status(SubscriptionStatus $status): self
    {
        return $this->state(['status' => $status]);
    }
}
