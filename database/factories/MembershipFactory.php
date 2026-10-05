<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
final class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<model-property<Membership>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => MembershipRole::Member,
        ];
    }

    public function role(MembershipRole $role): self
    {
        return $this->state(['role' => $role]);
    }
}
