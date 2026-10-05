<?php

declare(strict_types=1);

use App\Billing\BillingService;
use App\Billing\Gateways\AsaasGateway;
use App\Billing\SubscriptionStateMachine;
use App\Enums\InvoiceStatus;
use App\Enums\MembershipRole;
use App\Enums\SubscriptionStatus;
use App\Models\BillingWebhookEvent;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->tenant = subscribedTenant('starter', SubscriptionStatus::Trialing);
    $this->owner = memberOf($this->tenant);
});

function customerOf(Tenant $tenant): string
{
    return $tenant->refresh()->billing_customer_id;
}

it('inicia trial no provisionamento (idempotente)', function (): void {
    (new Database\Seeders\PlanSeeder)->run();
    $tenant = Tenant::factory()->create();

    inTenant($tenant, function (): void {
        app(BillingService::class)->startTrial();
        app(BillingService::class)->startTrial();

        $subscription = Subscription::query()->sole();
        expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
            ->and($subscription->trial_ends_at?->isFuture())->toBeTrue()
            ->and($subscription->gateway_subscription_id)->not->toBeNull();
    });

    expect($tenant->refresh()->billing_customer_id)->toStartWith('cus_');
});

it('recusa transições fora da máquina de estados', function (): void {
    inTenant($this->tenant, function (): void {
        $subscription = Subscription::query()->sole();
        $machine = app(SubscriptionStateMachine::class);

        $machine->transition($subscription, SubscriptionStatus::Canceled, 'teste');
        $machine->transition($subscription, SubscriptionStatus::Active, 'teste');
    });
})->throws(LogicException::class, 'canceled → active');

it('processa pagamento via webhook: fatura paga e assinatura ativa', function (): void {
    fakeWebhook(['id' => 'evt_1', 'type' => 'invoice.paid', 'customer' => customerOf($this->tenant), 'invoice' => 'inv_1', 'amount' => 4900, 'method' => 'pix'])
        ->assertStatus(202);

    inTenant($this->tenant, function (): void {
        expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Active)
            ->and(Invoice::query()->sole())->status->toBe(InvoiceStatus::Paid)->amount_cents->toBe(4900);
    });
});

it('é idempotente com reenvios e eventos fora de ordem', function (): void {
    $customer = customerOf($this->tenant);

    fakeWebhook(['id' => 'evt_pago', 'type' => 'invoice.paid', 'customer' => $customer, 'invoice' => 'inv_9', 'amount' => 4900])->assertStatus(202);
    fakeWebhook(['id' => 'evt_pago', 'type' => 'invoice.paid', 'customer' => $customer, 'invoice' => 'inv_9', 'amount' => 4900])->assertJson(['status' => 'duplicate']);
    fakeWebhook(['id' => 'evt_criado', 'type' => 'invoice.created', 'customer' => $customer, 'invoice' => 'inv_9', 'amount' => 4900])->assertStatus(202);

    expect(BillingWebhookEvent::query()->count())->toBe(2);
    inTenant($this->tenant, fn () => expect(Invoice::query()->sole()->status)->toBe(InvoiceStatus::Paid));
});

it('rejeita webhook com assinatura inválida', function (): void {
    $this->postJson(centralUrl('webhooks/billing/fake'), ['id' => 'x', 'type' => 'invoice.paid'], ['X-Fake-Signature' => 'errada'])
        ->assertUnauthorized();

    expect(BillingWebhookEvent::query()->count())->toBe(0);
});

it('não aplica webhook de um cliente em outro tenant', function (): void {
    $other = subscribedTenant();

    fakeWebhook(['id' => 'evt_x', 'type' => 'invoice.overdue', 'customer' => customerOf($other), 'invoice' => 'inv_x']);

    inTenant($this->tenant, fn () => expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Trialing));
    inTenant($other, fn () => expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::PastDue));
});

it('inadimplente → período de graça → suspensa (somente leitura) → reativada no pagamento', function (): void {
    $customer = customerOf($this->tenant);
    fakeWebhook(['id' => 'evt_od', 'type' => 'invoice.overdue', 'customer' => $customer, 'invoice' => 'inv_od', 'amount' => 4900]);

    inTenant($this->tenant, function (): void {
        $subscription = Subscription::query()->sole();
        expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
            ->and($subscription->grace_ends_at?->isFuture())->toBeTrue();
    });

    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'projects'), ['name' => 'Ainda pode'])->assertRedirect();

    $this->travel(8)->days();
    $this->artisan('billing:enforce-deadlines')->assertSuccessful();

    inTenant($this->tenant, fn () => expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Suspended));

    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'projects'), ['name' => 'Bloqueado'])->assertStatus(423);
    $this->actingAs($this->owner)->get(tenantUrl($this->tenant, 'projects'))->assertOk();
    $this->actingAs($this->owner)->get(tenantUrl($this->tenant, 'billing'))->assertOk();

    fakeWebhook(['id' => 'evt_ok', 'type' => 'invoice.paid', 'customer' => $customer, 'invoice' => 'inv_od', 'amount' => 4900])->assertStatus(202);
    expect(BillingWebhookEvent::query()->where('event_id', 'evt_ok')->value('error'))->toBeNull();

    inTenant($this->tenant, fn () => expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Active));
    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'projects'), ['name' => 'Liberado'])->assertRedirect();
});

it('fim do trial sem pagamento vira inadimplente', function (): void {
    inTenant($this->tenant, fn () => Subscription::query()->sole()->forceFill(['trial_ends_at' => now()->subMinute()])->save());

    $this->artisan('billing:enforce-deadlines')->assertSuccessful();

    inTenant($this->tenant, fn () => expect(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::PastDue));
});

it('faz upgrade com cobrança proporcional', function (): void {
    $tenant = subscribedTenant('starter');
    $owner = memberOf($tenant);
    $pro = Plan::query()->where('code', 'pro')->sole();

    $this->actingAs($owner)->post(tenantUrl($tenant, 'billing/plan'), ['plan_id' => $pro->id])->assertSessionHasNoErrors();

    inTenant($tenant, function () use ($pro): void {
        expect(Subscription::query()->sole()->plan_id)->toBe($pro->id);
        $invoice = Invoice::query()->sole();
        expect($invoice->amount_cents)->toBeGreaterThan(9000)->toBeLessThanOrEqual(10000)
            ->and($invoice->description)->toContain('Starter → Pro');
    });
});

it('bloqueia downgrade quando o uso excede o novo plano', function (): void {
    $tenant = subscribedTenant('pro');
    $owner = memberOf($tenant);
    foreach (range(1, 3) as $i) {
        memberOf($tenant, MembershipRole::Member);
    }
    $starter = Plan::query()->where('code', 'starter')->sole();

    $this->actingAs($owner)->post(tenantUrl($tenant, 'billing/plan'), ['plan_id' => $starter->id])->assertSessionHasErrors('plan');
});

it('só o dono acessa a cobrança', function (): void {
    $admin = memberOf($this->tenant, MembershipRole::Admin);

    $this->actingAs($admin)->get(tenantUrl($this->tenant, 'billing'))->assertForbidden();
});

it('valida o token de webhook do Asaas e normaliza eventos', function (): void {
    config()->set('billing.gateways.asaas.webhook_token', 'tok');
    $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ASAAS_ACCESS_TOKEN' => 'tok'], json_encode([
        'id' => 'evt_a', 'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_1', 'customer' => 'cus_1', 'value' => 49.9, 'billingType' => 'PIX', 'dueDate' => '2026-10-10'],
    ]));

    $event = (new AsaasGateway)->parseWebhook($request);

    expect($event->type->value)->toBe('invoice.paid')
        ->and($event->amountCents)->toBe(4990)
        ->and($event->method?->value)->toBe('pix');

    $request->headers->set('asaas-access-token', 'errado');
    (new AsaasGateway)->parseWebhook($request);
})->throws(App\Billing\Exceptions\InvalidWebhookSignature::class);
