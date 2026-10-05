<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Audit\AuditLogger;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize(Permission::ManageApiTokens->value);
        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('ApiTokens/Index', [
            'tokens' => $user->tokens()->latest()->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
            'abilities' => array_map(static fn (Permission $p): string => $p->value, Permission::cases()),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::ManageApiTokens->value);
        $user = $request->user();
        assert($user instanceof User);

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::enum(Permission::class)],
        ]);

        // Um token nunca recebe permissões além das do papel de quem o cria.
        $allowed = array_map(static fn (Permission $p): string => $p->value, $user->membership()?->role->permissions() ?? []);
        $requested = array_filter((array) $request->input('abilities'), is_string(...));
        $abilities = array_values(array_intersect($requested, $allowed));
        $name = $request->string('name')->value();

        $token = $user->createToken($name, $abilities, now()->addYear());
        $audit->record('api_token.created', ['name' => $name, 'abilities' => $abilities], $token->accessToken);

        return back()->with('status', 'Token criado.')->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, PersonalAccessToken $token, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::ManageApiTokens->value);
        $user = $request->user();
        assert($user instanceof User);

        abort_unless($token->tokenable_id === $user->id, 404);
        $token->delete();
        $audit->record('api_token.revoked', ['name' => $token->name], $token);

        return back();
    }
}
