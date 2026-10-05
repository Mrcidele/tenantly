<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Auth\TenantHandoff;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Onboarding\SignUp;
use App\Rules\ValidSubdomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class SignupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Signup/Create', ['centralDomain' => Tenant::centralDomain()]);
    }

    public function store(Request $request, SignUp $signUp): RedirectResponse
    {
        $request->merge(['subdomain' => Str::lower(trim((string) $request->string('subdomain')))]);

        $request->validate([
            'organization' => ['required', 'string', 'max:120'],
            'subdomain' => ['required', 'string', new ValidSubdomain],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);

        $result = $signUp->handle(
            $request->string('organization')->value(),
            $request->string('subdomain')->value(),
            $request->string('name')->value(),
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        // Só esta sessão pode acompanhar o provisionamento (evita enumeração).
        $request->session()->put('signup', ['tenant_id' => $result['tenant']->id, 'user_id' => $result['user']->id]);

        return redirect()->route('central.signup.status', $result['tenant']);
    }

    public function status(Request $request, Tenant $tenant): Response|JsonResponse
    {
        $this->ensureOwnSignup($request, $tenant);

        $steps = $tenant->provisioning['steps'] ?? [];
        $payload = [
            'status' => $tenant->status->value,
            'ready' => $tenant->status === TenantStatus::Active,
            'error' => $tenant->provisioning['error'] ?? null,
            'steps' => array_map(
                static fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'done' => is_array($steps) && isset($steps[$key])],
                array_keys(ProvisionTenant::STEPS),
                ProvisionTenant::STEPS,
            ),
        ];

        return $request->wantsJson() && ! $request->header('X-Inertia')
            ? response()->json($payload)
            : Inertia::render('Signup/Status', ['tenant' => ['id' => $tenant->id, 'name' => $tenant->name], 'provisioning' => $payload]);
    }

    public function continue(Request $request, Tenant $tenant, TenantHandoff $handoff): HttpResponse
    {
        $signup = $this->ensureOwnSignup($request, $tenant);
        abort_unless($tenant->status === TenantStatus::Active, 409, 'A organização ainda está sendo preparada.');

        $request->session()->forget('signup');

        return Inertia::location($handoff->issue($signup['user_id'], $tenant));
    }

    /**
     * @return array{tenant_id: string, user_id: string}
     */
    private function ensureOwnSignup(Request $request, Tenant $tenant): array
    {
        $signup = $request->session()->get('signup');

        abort_unless(is_array($signup) && ($signup['tenant_id'] ?? null) === $tenant->id && is_string($signup['user_id'] ?? null), 404);

        /** @var array{tenant_id: string, user_id: string} $signup */
        return $signup;
    }
}
