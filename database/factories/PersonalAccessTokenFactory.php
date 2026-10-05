<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PersonalAccessToken>
 */
final class PersonalAccessTokenFactory extends Factory
{
    protected $model = PersonalAccessToken::class;

    /**
     * @return array<model-property<PersonalAccessToken>, mixed>
     */
    public function definition(): array
    {
        return [
            'tokenable_type' => (new User)->getMorphClass(),
            'tokenable_id' => (string) Str::uuid7(),
            'name' => 'token',
            'token' => hash('sha256', Str::random(40)),
            'abilities' => ['*'],
        ];
    }
}
