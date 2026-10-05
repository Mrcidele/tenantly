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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Stripe via API REST, com verificação do header Stripe-Signature (HMAC + tolerância). */
final class StripeGateway implements BillingGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCustomer(Tenant $tenant, string $email): string
    {
        return $this->id($this->http()->asForm()->post('/customers', [
            'name' => $tenant->name,
            'email' => $email,
            'metadata[tenant_id]' => $tenant->id,
        ])->throw()->json());
    }

    public function createSubscription(Tenant $tenant, Plan $plan, Subscription $subscription): GatewaySubscription
    {
        return new GatewaySubscription($this->id($this->http()->asForm()->post('/subscriptions', array_filter([
            'customer' => $tenant->billing_customer_id,
            'items[0][price]' => $this->price($plan),
            'trial_end' => $subscription->trial_ends_at?->getTimestamp(),
            'payment_behavior' => 'default_incomplete',
            'metadata[subscription_id]' => $subscription->id,
        ], static fn (mixed $value): bool => $value !== null))->throw()->json()));
    }

    public function swapPlan(Subscription $subscription, Plan $plan): void
    {
        $itemId = $this->http()->get('/subscriptions/'.$subscription->gateway_subscription_id)->throw()->json('items.data.0.id');

        $this->http()->asForm()->post('/subscriptions/'.$subscription->gateway_subscription_id, [
            'items[0][id]' => is_string($itemId) ? $itemId : '',
            'items[0][price]' => $this->price($plan),
            'proration_behavior' => 'always_invoice',
        ])->throw();
    }

    public function charge(Tenant $tenant, int $amountCents, string $description): string
    {
        $this->http()->asForm()->post('/invoiceitems', [
            'customer' => $tenant->billing_customer_id,
            'amount' => $amountCents,
            'currency' => 'brl',
            'description' => $description,
        ])->throw();

        return $this->id($this->http()->asForm()->post('/invoices', [
            'customer' => $tenant->billing_customer_id,
            'auto_advance' => 'true',
        ])->throw()->json());
    }

    public function cancel(Subscription $subscription): void
    {
        $this->http()->delete('/subscriptions/'.$subscription->gateway_subscription_id)->throw();
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $secret = config('billing.gateways.stripe.webhook_secret');
        $tolerance = config('billing.gateways.stripe.webhook_tolerance');

        if (! is_string($secret) || ! self::validSignature($request->getContent(), (string) $request->header('Stripe-Signature'), $secret, is_int($tolerance) ? $tolerance : 300)) {
            throw new InvalidWebhookSignature;
        }

        $type = (string) $request->string('type');
        /** @var array<string, mixed> $object */
        $object = (array) $request->input('data.object', []);

        $normalized = match ($type) {
            'invoice.created', 'invoice.finalized' => WebhookEventType::InvoiceCreated,
            'invoice.paid' => WebhookEventType::InvoicePaid,
            'invoice.payment_failed' => WebhookEventType::InvoiceOverdue,
            'customer.subscription.deleted' => WebhookEventType::SubscriptionCanceled,
            default => WebhookEventType::Ignored,
        };

        $isSubscription = str_starts_with($type, 'customer.subscription');

        return new WebhookEvent(
            id: (string) $request->string('id'),
            type: $normalized,
            customerId: is_string($object['customer'] ?? null) ? $object['customer'] : null,
            subscriptionId: $isSubscription ? (is_string($object['id'] ?? null) ? $object['id'] : null) : (is_string($object['subscription'] ?? null) ? $object['subscription'] : null),
            invoiceId: ! $isSubscription && is_string($object['id'] ?? null) ? $object['id'] : null,
            amountCents: is_int($object['amount_due'] ?? null) ? $object['amount_due'] : null,
            dueAt: is_int($object['due_date'] ?? null) ? CarbonImmutable::createFromTimestamp($object['due_date']) : null,
            method: $isSubscription ? null : PaymentMethod::Card,
            paymentUrl: is_string($object['hosted_invoice_url'] ?? null) ? $object['hosted_invoice_url'] : null,
            payload: $request->json()->all(),
        );
    }

    public static function validSignature(string $payload, string $header, string $secret, int $tolerance): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function price(Plan $plan): string
    {
        $price = config('billing.gateways.stripe.prices.'.$plan->code);

        return is_string($price) ? $price : throw new RuntimeException("Plano [{$plan->code}] sem price no Stripe.");
    }

    private function http(): PendingRequest
    {
        $secret = config('billing.gateways.stripe.secret');
        $base = config('billing.gateways.stripe.base_url');

        return Http::baseUrl(is_string($base) ? $base : '')
            ->withToken(is_string($secret) ? $secret : '')
            ->timeout(15);
    }

    private function id(mixed $response): string
    {
        $id = is_array($response) ? ($response['id'] ?? null) : null;

        return is_string($id) ? $id : throw new RuntimeException('Resposta inesperada do Stripe.');
    }
}
