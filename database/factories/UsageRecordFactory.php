<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageRecord>
 */
final class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    /**
     * @return array<model-property<UsageRecord>, mixed>
     */
    public function definition(): array
    {
        return [
            'metric' => 'api_calls_per_month',
            'period' => fake()->unique()->numerify('2026-##'),
            'value' => fake()->numberBetween(1, 1000),
        ];
    }
}
