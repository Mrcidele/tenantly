<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Auth\TenantHandoff;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class HandoffController extends Controller
{
    public function __invoke(Request $request, TenantHandoff $handoff, TenantContext $context): RedirectResponse
    {
        $payload = $handoff->consume((string) $request->string('token'), $context->get());
        abort_if($payload === null, 403, 'Link expirado ou inválido.');

        $context->setUser($payload['user_id']);
        $user = User::query()->find($payload['user_id']);
        abort_if($user === null || $user->membership() === null, 403);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $context->id());

        foreach (['impersonation_id', 'impersonator_id'] as $key) {
            if (isset($payload['extra'][$key])) {
                $request->session()->put($key, $payload['extra'][$key]);
            }
        }

        return redirect('/');
    }
}
