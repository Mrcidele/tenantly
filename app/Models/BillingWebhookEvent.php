<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Concerns\UsesCentralConnection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de webhooks recebidos: a unicidade (gateway, event_id) garante
 * processamento idempotente mesmo com reenvios do gateway.
 *
 * @property string $id
 * @property string $gateway
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property string|null $error
 */
#[Fillable(['gateway', 'event_id', 'type', 'payload', 'processed_at', 'error'])]
class BillingWebhookEvent extends Model
{
    use HasUuids, UsesCentralConnection;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'immutable_datetime'];
    }
}
