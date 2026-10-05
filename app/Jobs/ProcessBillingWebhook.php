<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Billing\Data\WebhookEvent;
use App\Billing\WebhookProcessor;
use App\Enums\PaymentMethod;
use App\Enums\WebhookEventType;
use App\Models\BillingWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ProcessBillingWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly string $webhookEventId) {}

    public function handle(WebhookProcessor $processor): void
    {
        // Lock + processed_at: o mesmo evento nunca é aplicado duas vezes.
        $store = Cache::store('central')->getStore();
        assert($store instanceof LockProvider);

        $store->lock('billing-webhook:'.$this->webhookEventId, 120)->block(10, function () use ($processor): void {
            $record = BillingWebhookEvent::query()->findOrFail($this->webhookEventId);

            if ($record->processed_at !== null) {
                return;
            }

            $processor->process($record->gateway, self::rehydrate($record));

            $record->update(['processed_at' => now(), 'error' => null]);
        });
    }

    public function failed(Throwable $exception): void
    {
        BillingWebhookEvent::query()->whereKey($this->webhookEventId)->update(['error' => mb_substr($exception->getMessage(), 0, 2000)]);
    }

    private static function rehydrate(BillingWebhookEvent $record): WebhookEvent
    {
        /** @var array<string, mixed> $n */
        $n = is_array($record->payload['normalized'] ?? null) ? $record->payload['normalized'] : [];
        $str = static fn (string $key): ?string => is_string($n[$key] ?? null) ? $n[$key] : null;

        return new WebhookEvent(
            id: $record->event_id,
            type: WebhookEventType::tryFrom($record->type) ?? WebhookEventType::Ignored,
            customerId: $str('customer_id'),
            subscriptionId: $str('subscription_id'),
            invoiceId: $str('invoice_id'),
            amountCents: is_int($n['amount_cents'] ?? null) ? $n['amount_cents'] : null,
            dueAt: $str('due_at') !== null ? CarbonImmutable::parse($str('due_at')) : null,
            method: PaymentMethod::tryFrom($str('method') ?? ''),
            paymentUrl: $str('payment_url'),
        );
    }
}
