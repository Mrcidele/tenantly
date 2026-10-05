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

/**
 * Asaas: Pix e boleto (billingType UNDEFINED deixa o cliente escolher).
 * Webhooks autenticados pelo header `asaas-access-token`.
 */
final class AsaasGateway implements BillingGateway
{
    public function name(): string
    {
        return 'asaas';
    }

    public function createCustomer(Tenant $tenant, string $email): string
    {
        return $this->id($this->http()->post('/customers', [
            'name' => $tenant->name,
            'email' => $email,
            'externalReference' => $tenant->id,
        ])->throw()->json());
    }

    public function createSubscription(Tenant $tenant, Plan $plan, Subscription $subscription): GatewaySubscription
    {
        $firstDue = $subscription->trial_ends_at ?? CarbonImmutable::now();

        return new GatewaySubscription($this->id($this->http()->post('/subscriptions', [
            'customer' => $tenant->billing_customer_id,
            'billingType' => 'UNDEFINED',
            'value' => $plan->price_cents / 100,
            'nextDueDate' => $firstDue->toDateString(),
            'cycle' => $plan->interval->value === 'year' ? 'YEARLY' : 'MONTHLY',
            'description' => $plan->name,
            'externalReference' => $subscription->id,
        ])->throw()->json()));
    }

    public function swapPlan(Subscription $subscription, Plan $plan): void
    {
        $this->http()->put('/subscriptions/'.$subscription->gateway_subscription_id, [
            'value' => $plan->price_cents / 100,
            'cycle' => $plan->interval->value === 'year' ? 'YEARLY' : 'MONTHLY',
            'description' => $plan->name,
            'updatePendingPayments' => true,
        ])->throw();
    }

    public function charge(Tenant $tenant, int $amountCents, string $description): string
    {
        return $this->id($this->http()->post('/payments', [
            'customer' => $tenant->billing_customer_id,
            'billingType' => 'UNDEFINED',
            'value' => $amountCents / 100,
            'dueDate' => CarbonImmutable::now()->addDays(3)->toDateString(),
            'description' => $description,
        ])->throw()->json());
    }

    public function cancel(Subscription $subscription): void
    {
        $this->http()->delete('/subscriptions/'.$subscription->gateway_subscription_id)->throw();
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $token = config('billing.gateways.asaas.webhook_token');

        if (! is_string($token) || $token === '' || ! hash_equals($token, (string) $request->header('asaas-access-token'))) {
            throw new InvalidWebhookSignature;
        }

        $event = (string) $request->string('event');
        /** @var array<string, mixed> $payment */
        $payment = (array) $request->input('payment', []);
        /** @var array<string, mixed> $subscription */
        $subscription = (array) $request->input('subscription', []);

        $type = match ($event) {
            'PAYMENT_CREATED' => WebhookEventType::InvoiceCreated,
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => WebhookEventType::InvoicePaid,
            'PAYMENT_OVERDUE' => WebhookEventType::InvoiceOverdue,
            'SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED' => WebhookEventType::SubscriptionCanceled,
            default => WebhookEventType::Ignored,
        };

        $value = $payment['value'] ?? null;

        return new WebhookEvent(
            id: (string) $request->string('id'),
            type: $type,
            customerId: self::str($payment['customer'] ?? $subscription['customer'] ?? null),
            subscriptionId: self::str($payment['subscription'] ?? $subscription['id'] ?? null),
            invoiceId: self::str($payment['id'] ?? null),
            amountCents: is_numeric($value) ? (int) round((float) $value * 100) : null,
            dueAt: is_string($payment['dueDate'] ?? null) ? CarbonImmutable::parse($payment['dueDate']) : null,
            method: match ($payment['billingType'] ?? null) {
                'PIX' => PaymentMethod::Pix,
                'BOLETO' => PaymentMethod::Boleto,
                'CREDIT_CARD' => PaymentMethod::Card,
                default => null,
            },
            paymentUrl: self::str($payment['invoiceUrl'] ?? null),
            payload: $request->json()->all(),
        );
    }

    private function http(): PendingRequest
    {
        $key = config('billing.gateways.asaas.api_key');
        $base = config('billing.gateways.asaas.base_url');

        return Http::baseUrl(is_string($base) ? $base : '')
            ->withHeaders(['access_token' => is_string($key) ? $key : ''])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 500, throw: false);
    }

    private function id(mixed $response): string
    {
        $id = is_array($response) ? ($response['id'] ?? null) : null;

        return is_string($id) ? $id : throw new RuntimeException('Resposta inesperada do Asaas.');
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
