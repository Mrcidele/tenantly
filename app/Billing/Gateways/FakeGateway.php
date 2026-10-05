<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Billing\BillingGateway;
use App\Billing\Data\GatewaySubscription;
use App\Billing\Data\WebhookEvent;
use App\Billing\Exceptions\InvalidWebhookSignature;
use App\Enums\PaymentMethod;
use App\Enums\WebhookEventType;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gateway local para desenvolvimento e testes. Webhooks assinados com
 * HMAC-SHA256 no header X-Fake-Signature, como um gateway real.
 */
final class FakeGateway implements BillingGateway
{
    /** @var list<array{type: string, args: array<string, mixed>}> */
    public array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    public function createCustomer(Tenant $tenant, string $email): string
    {
        $this->calls[] = ['type' => 'customer', 'args' => ['tenant' => $tenant->id, 'email' => $email]];

        return 'cus_'.Str::lower(Str::random(14));
    }

    public function createSubscription(Tenant $tenant, Plan $plan, Subscription $subscription): GatewaySubscription
    {
        $this->calls[] = ['type' => 'subscription', 'args' => ['plan' => $plan->code]];

        return new GatewaySubscription('sub_'.Str::lower(Str::random(14)));
    }

    public function swapPlan(Subscription $subscription, Plan $plan): void
    {
        $this->calls[] = ['type' => 'swap', 'args' => ['plan' => $plan->code]];
    }

    public function charge(Tenant $tenant, int $amountCents, string $description): string
    {
        $this->calls[] = ['type' => 'charge', 'args' => ['amount' => $amountCents, 'description' => $description]];

        return 'inv_'.Str::lower(Str::random(14));
    }

    public function cancel(Subscription $subscription): void
    {
        $this->calls[] = ['type' => 'cancel', 'args' => []];
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $secret = config('billing.gateways.fake.webhook_secret');
        $expected = hash_hmac('sha256', $request->getContent(), is_string($secret) ? $secret : '');

        if (! hash_equals($expected, (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidWebhookSignature;
        }

        /** @var array<string, mixed> $data */
        $data = $request->json()->all();

        return new WebhookEvent(
            id: self::string($data, 'id') ?? throw new InvalidWebhookSignature,
            type: WebhookEventType::tryFrom(self::string($data, 'type') ?? '') ?? WebhookEventType::Ignored,
            customerId: self::string($data, 'customer'),
            subscriptionId: self::string($data, 'subscription'),
            invoiceId: self::string($data, 'invoice'),
            amountCents: is_int($data['amount'] ?? null) ? $data['amount'] : null,
            dueAt: self::string($data, 'due_at') !== null ? CarbonImmutable::parse(self::string($data, 'due_at')) : null,
            method: PaymentMethod::tryFrom(self::string($data, 'method') ?? ''),
            paymentUrl: self::string($data, 'payment_url'),
            payload: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        return is_string($data[$key] ?? null) ? $data[$key] : null;
    }
}
