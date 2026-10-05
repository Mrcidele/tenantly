<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<model-property<Project>, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::ucfirst(fake()->word().' '.fake()->word()),
            'description' => fake()->sentence(),
        ];
    }
}
