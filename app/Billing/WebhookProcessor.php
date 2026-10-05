<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Data\WebhookEvent;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WebhookEventType;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Aplica um evento normalizado ao tenant dono do cliente no gateway. O tenant
 * vem de tenants.billing_customer_id (central), então não há leitura
 * cross-tenant: todo o resto roda dentro do contexto do tenant.
 */
final readonly class WebhookProcessor
{
    public function __construct(
        private TenantContext $context,
        private SubscriptionStateMachine $states,
    ) {}

    public function process(string $gateway, WebhookEvent $event): void
    {
        if ($event->type === WebhookEventType::Ignored || $event->customerId === null) {
            return;
        }

        $tenant = Tenant::query()
            ->where('billing_gateway', $gateway)
            ->where('billing_customer_id', $event->customerId)
            ->first();

        if ($tenant === null) {
            return;
        }

        $this->context->run($tenant, function () use ($event): void {
            $subscription = Subscription::query()->first();

            if ($subscription === null) {
                return;
            }

            match ($event->type) {
                WebhookEventType::InvoiceCreated => $this->upsertInvoice($subscription, $event, InvoiceStatus::Open),
                WebhookEventType::InvoicePaid => $this->paid($subscription, $event),
                WebhookEventType::InvoiceOverdue => $this->overdue($subscription, $event),
                default => $this->states->transition($subscription, SubscriptionStatus::Canceled, 'Cancelada no gateway'),
            };
        });
    }

    private function paid(Subscription $subscription, WebhookEvent $event): void
    {
        $this->upsertInvoice($subscription, $event, InvoiceStatus::Paid);

        if ($subscription->status !== SubscriptionStatus::Canceled) {
            // Pagamento recebido inicia/renova o ciclo.
            $start = CarbonImmutable::now();
            $subscription->current_period_starts_at = $start;
            $subscription->current_period_ends_at = $subscription->plan->interval->addTo($start);
            $subscription->save();

            $this->states->transition($subscription, SubscriptionStatus::Active, 'Pagamento confirmado');
        }
    }

    private function overdue(Subscription $subscription, WebhookEvent $event): void
    {
        $this->upsertInvoice($subscription, $event, InvoiceStatus::Overdue);

        if (in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
            $this->states->transition($subscription, SubscriptionStatus::PastDue, 'Fatura vencida');
        }
    }

    private function upsertInvoice(Subscription $subscription, WebhookEvent $event, InvoiceStatus $status): void
    {
        if ($event->invoiceId === null) {
            return;
        }

        $invoice = Invoice::query()->firstOrNew(['gateway_invoice_id' => $event->invoiceId]);

        // Eventos podem chegar fora de ordem: "paga" nunca volta para "aberta".
        if ($invoice->exists && $invoice->status === InvoiceStatus::Paid) {
            return;
        }

        $invoice->fill([
            'subscription_id' => $subscription->id,
            'amount_cents' => $event->amountCents ?? ($invoice->exists ? $invoice->amount_cents : $subscription->plan->price_cents),
            'status' => $status,
            'method' => $event->method,
            'description' => $invoice->exists ? $invoice->description : 'Assinatura '.$subscription->plan->name,
            'payment_url' => $event->paymentUrl ?? ($invoice->exists ? $invoice->payment_url : null),
            'due_at' => $event->dueAt ?? ($invoice->exists ? $invoice->due_at : null),
            'paid_at' => $status === InvoiceStatus::Paid ? CarbonImmutable::now() : null,
        ])->save();
    }
}
