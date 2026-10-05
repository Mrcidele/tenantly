<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<model-property<AuditLog>, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => 'system',
            'action' => 'test.'.fake()->word(),
            'properties' => ['ok' => true],
        ];
    }
}
