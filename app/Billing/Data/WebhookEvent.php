<?php

declare(strict_types=1);

namespace App\Billing\Data;

use App\Enums\PaymentMethod;
use App\Enums\WebhookEventType;
use Carbon\CarbonImmutable;

/** Evento de webhook já verificado e normalizado. */
final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public WebhookEventType $type,
        public ?string $customerId,
        public ?string $subscriptionId,
        public ?string $invoiceId = null,
        public ?int $amountCents = null,
        public ?CarbonImmutable $dueAt = null,
        public ?PaymentMethod $method = null,
        public ?string $paymentUrl = null,
        public array $payload = [],
    ) {}
}
