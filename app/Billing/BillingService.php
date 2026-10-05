<?php

declare(strict_types=1);

namespace App\Billing;

use App\Audit\AuditLogger;
use App\Billing\Data\Proration;
use App\Entitlements\Entitlements;
use App\Enums\InvoiceStatus;
use App\Enums\Limit;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class BillingService
{
    public function __construct(
        private TenantContext $context,
        private GatewayManager $gateways,
        private ProrationCalculator $proration,
        private SubscriptionStateMachine $states,
        private Entitlements $entitlements,
        private AuditLogger $audit,
    ) {}

    public function current(): ?Subscription
    {
        return Subscription::query()->with('plan.features')->first();
    }

    /** Idempotente: chamado no provisionamento. */
    public function startTrial(?User $owner = null): Subscription
    {
        if (($existing = $this->current()) !== null) {
            return $existing;
        }

        $tenant = $this->context->get();
        $plan = $this->defaultPlan();
        $gateway = $this->gateways->default();

        if ($tenant->billing_customer_id === null) {
            $email = $owner->email ?? Membership::query()->with('user')->oldest()->first()?->user->email ?? 'billing@'.$tenant->slug.'.invalid';
            $tenant->forceFill([
                'billing_gateway' => $gateway->name(),
                'billing_customer_id' => $gateway->createCustomer($tenant, $email),
            ])->save();
        }

        $now = CarbonImmutable::now();
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'gateway' => $gateway->name(),
            'trial_ends_at' => $now->addDays($plan->trial_days),
            'current_period_starts_at' => $now,
            'current_period_ends_at' => $now->addDays($plan->trial_days),
        ]);
        $subscription->status = SubscriptionStatus::Trialing;
        $subscription->save();

        $subscription->gateway_subscription_id = $gateway->createSubscription($tenant, $plan, $subscription)->id;
        $subscription->save();

        $this->audit->record('subscription.trial_started', ['plan' => $plan->code], $subscription);

        return $subscription;
    }

    public function previewChange(Subscription $subscription, Plan $plan): Proration
    {
        return $this->proration->calculate(
            $subscription->plan,
            $plan,
            $subscription->current_period_starts_at ?? CarbonImmutable::now(),
            $subscription->current_period_ends_at ?? CarbonImmutable::now(),
            CarbonImmutable::now(),
        );
    }

    /** Upgrade/downgrade com proração. Downgrade exige que o uso caiba no novo plano. */
    public function changePlan(Subscription $subscription, Plan $plan): Subscription
    {
        if (! $subscription->status->allowsWrites() && $subscription->status !== SubscriptionStatus::Suspended) {
            throw ValidationException::withMessages(['plan' => 'Assinatura cancelada não pode trocar de plano.']);
        }

        if ($plan->id === $subscription->plan_id || ! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => 'Plano inválido.']);
        }

        foreach ($this->entitlements->violationsFor($plan) as $limit => $usage) {
            throw ValidationException::withMessages([
                'plan' => sprintf('Seu uso de %s (%d) excede o limite do plano %s.', Limit::from($limit)->label(), $usage, $plan->name),
            ]);
        }

        $tenant = $this->context->get();
        $gateway = $this->gateways->get($subscription->gateway);
        $fromPlan = $subscription->plan;
        $isTrial = $subscription->status === SubscriptionStatus::Trialing;
        $proration = $isTrial ? null : $this->previewChange($subscription, $plan);

        $gateway->swapPlan($subscription, $plan);

        if ($proration !== null && $proration->netCents() > 0) {
            $description = "Ajuste proporcional: {$fromPlan->name} → {$plan->name}";
            Invoice::query()->create([
                'subscription_id' => $subscription->id,
                'gateway_invoice_id' => $gateway->charge($tenant, $proration->netCents(), $description),
                'amount_cents' => $proration->netCents(),
                'status' => InvoiceStatus::Open,
                'description' => $description,
                'due_at' => now()->addDays(3),
            ]);
        }

        $subscription->plan_id = $plan->id;

        if ($proration !== null) {
            $subscription->current_period_starts_at = $proration->periodStartsAt;
            $subscription->current_period_ends_at = $proration->periodEndsAt;
        }

        $subscription->save();
        $subscription->unsetRelation('plan');
        $this->entitlements->flush();

        $this->audit->record('subscription.plan_changed', [
            'from' => $fromPlan->code,
            'to' => $plan->code,
            'net_cents' => $proration?->netCents() ?? 0,
        ], $subscription);

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $this->gateways->get($subscription->gateway)->cancel($subscription);

        $subscription->ends_at = $subscription->current_period_ends_at;

        return $this->states->transition($subscription, SubscriptionStatus::Canceled, 'Cancelado pelo cliente');
    }

    private function defaultPlan(): Plan
    {
        $code = config('billing.default_plan');

        return Plan::query()->where('code', is_string($code) ? $code : 'starter')->first()
            ?? Plan::query()->where('is_active', true)->orderBy('sort')->firstOrFail();
    }
}
