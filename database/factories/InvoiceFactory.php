<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<model-property<Invoice>, mixed>
     */
    public function definition(): array
    {
        return [
            'amount_cents' => 4900,
            'currency' => 'BRL',
            'status' => InvoiceStatus::Open,
            'description' => 'Mensalidade',
            'due_at' => now()->addDays(3),
        ];
    }
}
