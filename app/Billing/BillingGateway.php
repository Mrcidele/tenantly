<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Data\GatewaySubscription;
use App\Billing\Data\WebhookEvent;
use App\Billing\Exceptions\InvalidWebhookSignature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Contrato único para Stripe, Asaas (Pix/boleto) ou qualquer outro gateway.
 * O restante da aplicação só conhece esta interface e os eventos normalizados.
 */
interface BillingGateway
{
    public function name(): string;

    /** @return string id do cliente no gateway */
    public function createCustomer(Tenant $tenant, string $email): string;

    public function createSubscription(Tenant $tenant, Plan $plan, Subscription $subscription): GatewaySubscription;

    public function swapPlan(Subscription $subscription, Plan $plan): void;

    /** Cobrança avulsa (ex.: diferença proporcional num upgrade). Retorna o id da fatura. */
    public function charge(Tenant $tenant, int $amountCents, string $description): string;

    public function cancel(Subscription $subscription): void;

    /**
     * Verifica a assinatura e normaliza o evento.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): WebhookEvent;
}
