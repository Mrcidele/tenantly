<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DataExport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DataExport>
 */
final class DataExportFactory extends Factory
{
    protected $model = DataExport::class;

    /**
     * @return array<model-property<DataExport>, mixed>
     */
    public function definition(): array
    {
        return ['requested_by' => (string) Str::uuid7(), 'status' => 'pending'];
    }
}
