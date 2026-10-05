<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<model-property<Tenant>, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::limit(Str::slug($name), 40, '').'-'.Str::lower(Str::random(6)),
            'status' => TenantStatus::Active,
        ];
    }

    public function provisioning(): self
    {
        return $this->state(['status' => TenantStatus::Provisioning]);
    }

    public function suspended(): self
    {
        return $this->state(['status' => TenantStatus::Suspended, 'suspended_at' => now()]);
    }
}
