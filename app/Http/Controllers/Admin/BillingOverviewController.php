<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class BillingOverviewController extends Controller
{
    public function plans(): Response
    {
        return Inertia::render('Admin/Plans', [
            'plans' => Plan::query()->with('features')->orderBy('sort')->get(),
        ]);
    }

    public function invoices(TenantContext $context): Response
    {
        $invoices = $context->withoutTenancy('Painel central: listagem de faturas', fn () => Invoice::query()
            ->latest()
            ->limit(100)
            ->get(['id', 'tenant_id', 'amount_cents', 'status', 'method', 'description', 'due_at', 'paid_at']));

        return Inertia::render('Admin/Invoices', ['invoices' => $invoices]);
    }
}
