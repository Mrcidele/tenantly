<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\TenantAdministration;
use App\Auth\Impersonator;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->value();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => Tenant::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('slug', 'ilike', "%{$search}%")))
                ->latest()
                ->paginate(25, ['id', 'name', 'slug', 'status', 'created_at'])
                ->withQueryString(),
            'q' => $search,
        ]);
    }

    public function show(Request $request, Tenant $tenant, TenantAdministration $administration): Response
    {
        $overview = $administration->overview($tenant, $this->admin($request));

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant->only(['id', 'name', 'slug', 'status', 'limit_overrides', 'created_at', 'primary_domain']),
            'subscription' => $overview['subscription'] === null ? null : [
                'status' => $overview['subscription']->status->value,
                'plan' => $overview['subscription']->plan->name,
                'current_period_ends_at' => $overview['subscription']->current_period_ends_at,
            ],
            'members' => $overview['members'],
            'usage' => $overview['usage'],
        ]);
    }

    public function suspend(Request $request, Tenant $tenant, TenantAdministration $administration): RedirectResponse
    {
        $administration->suspend($tenant, $this->admin($request), $request->string('reason')->value());

        return back()->with('status', 'Tenant suspenso.');
    }

    public function reactivate(Request $request, Tenant $tenant, TenantAdministration $administration): RedirectResponse
    {
        $administration->reactivate($tenant, $this->admin($request), $request->string('reason')->value());

        return back()->with('status', 'Tenant reativado.');
    }

    public function limits(Request $request, Tenant $tenant, TenantAdministration $administration): RedirectResponse
    {
        $request->validate(['overrides' => ['required', 'array'], 'reason' => ['required', 'string']]);

        /** @var array<string, int|bool|null> $overrides */
        $overrides = array_filter((array) $request->input('overrides'), static fn (mixed $v): bool => is_int($v) || is_bool($v) || $v === null);

        $administration->adjustLimits($tenant, $this->admin($request), $overrides, $request->string('reason')->value());

        return back()->with('status', 'Limites ajustados.');
    }

    public function queue(Request $request, Tenant $tenant, TenantAdministration $administration): RedirectResponse
    {
        $administration->assignQueue(
            $tenant,
            $this->admin($request),
            $request->filled('queue') ? $request->string('queue')->value() : null,
            $request->string('reason')->value(),
        );

        return back()->with('status', 'Fila do tenant atualizada.');
    }

    public function impersonate(Request $request, Tenant $tenant, Impersonator $impersonator): HttpResponse
    {
        $request->validate(['user_id' => ['required', 'uuid'], 'reason' => ['required', 'string']]);

        return Inertia::location($impersonator->start(
            $this->admin($request),
            $tenant,
            $request->string('user_id')->value(),
            $request->string('reason')->value(),
            $request,
        ));
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        assert($admin instanceof Admin);

        return $admin;
    }
}
