<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<model-property<Plan>, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('plan-????'),
            'name' => 'Plano '.fake()->word(),
            'price_cents' => 4900,
            'currency' => 'BRL',
            'interval' => BillingInterval::Month,
            'trial_days' => 14,
            'is_active' => true,
        ];
    }

    /**
     * @param  array<string, int|bool>  $values  chaves de Feature/Limit
     */
    public function withFeatures(array $values): self
    {
        return $this->afterCreating(function (Plan $plan) use ($values): void {
            foreach ($values as $feature => $value) {
                $plan->features()->create(['feature' => $feature, 'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value]);
            }
        });
    }

    public function starter(): self
    {
        return $this->state(['code' => 'starter', 'name' => 'Starter', 'price_cents' => 4900, 'sort' => 1])->withFeatures([
            Limit::Users->value => 3, Limit::Projects->value => 10, Limit::StorageMb->value => 1024,
            Limit::ApiCallsPerMonth->value => 10000, Feature::ApiAccess->value => true,
        ]);
    }

    public function pro(): self
    {
        return $this->state(['code' => 'pro', 'name' => 'Pro', 'price_cents' => 14900, 'sort' => 2])->withFeatures([
            Limit::Users->value => 20, Limit::Projects->value => -1, Limit::StorageMb->value => 10240,
            Limit::ApiCallsPerMonth->value => 100000, Feature::ApiAccess->value => true,
            Feature::CustomDomains->value => true, Feature::AuditLog->value => true, Feature::DataExport->value => true,
        ]);
    }
}
