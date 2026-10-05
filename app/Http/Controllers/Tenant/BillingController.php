<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Billing\BillingService;
use App\Entitlements\Entitlements;
use App\Enums\Limit;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function index(BillingService $billing, Entitlements $entitlements): Response
    {
        $this->authorize(Permission::ManageBilling->value);

        $subscription = $billing->current();

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'plan' => $subscription->plan->only(['id', 'code', 'name', 'price_cents', 'interval']),
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'grace_ends_at' => $subscription->grace_ends_at,
            ],
            'usage' => array_map(static fn (Limit $limit): array => [
                'key' => $limit->value,
                'label' => $limit->label(),
                'used' => $entitlements->usage($limit),
                'limit' => $entitlements->limit($limit),
            ], Limit::cases()),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort')->get(['id', 'code', 'name', 'price_cents', 'interval']),
            'invoices' => Invoice::query()->latest()->limit(24)->get(['id', 'amount_cents', 'status', 'method', 'description', 'payment_url', 'due_at', 'paid_at']),
        ]);
    }

    public function preview(Request $request, BillingService $billing): JsonResponse
    {
        $this->authorize(Permission::ManageBilling->value);
        $request->validate(['plan_id' => ['required', 'uuid']]);

        $subscription = $billing->current();
        abort_if($subscription === null, 404);
        $plan = Plan::query()->findOrFail($request->string('plan_id')->value());
        $proration = $billing->previewChange($subscription, $plan);

        return response()->json([
            'credit_cents' => $proration->creditCents,
            'charge_cents' => $proration->chargeCents,
            'net_cents' => $proration->netCents(),
        ]);
    }

    public function changePlan(Request $request, BillingService $billing): RedirectResponse
    {
        $this->authorize(Permission::ManageBilling->value);
        $request->validate(['plan_id' => ['required', 'uuid']]);

        $subscription = $billing->current();
        abort_if($subscription === null, 404);

        $billing->changePlan($subscription, Plan::query()->findOrFail($request->string('plan_id')->value()));

        return back()->with('status', 'Plano alterado.');
    }

    public function cancel(BillingService $billing): RedirectResponse
    {
        $this->authorize(Permission::ManageBilling->value);

        $subscription = $billing->current();
        abort_if($subscription === null, 404);
        $billing->cancel($subscription);

        return back()->with('status', 'Assinatura cancelada.');
    }
}
