<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DomainStatus;
use App\Models\TenantDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantDomain>
 */
final class TenantDomainFactory extends Factory
{
    protected $model = TenantDomain::class;

    /**
     * @return array<model-property<TenantDomain>, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => 'app.'.fake()->unique()->domainName(),
            'is_primary' => false,
        ];
    }

    public function verified(): self
    {
        return $this->state(['status' => DomainStatus::Verified, 'verified_at' => now()]);
    }
}
