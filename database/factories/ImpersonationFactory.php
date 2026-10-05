<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Impersonation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Impersonation>
 */
final class ImpersonationFactory extends Factory
{
    protected $model = Impersonation::class;

    /**
     * @return array<model-property<Impersonation>, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'user_id' => (string) Str::uuid7(),
            'reason' => 'Chamado #'.fake()->numberBetween(1000, 9999),
            'started_at' => now(),
        ];
    }
}
