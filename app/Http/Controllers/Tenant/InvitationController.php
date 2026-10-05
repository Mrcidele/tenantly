<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Members\AcceptInvitation;
use App\Actions\Members\InviteMember;
use App\Enums\MembershipRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationController extends Controller
{
    public function store(Request $request, InviteMember $invite): RedirectResponse
    {
        $this->authorize(Permission::ManageMembers->value);

        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(MembershipRole::class)],
        ]);

        $user = $request->user();
        assert($user instanceof User);

        $role = $request->enum('role', MembershipRole::class);
        assert($role instanceof MembershipRole);

        $invite->handle($user, $request->string('email')->value(), $role);

        return back()->with('status', 'Convite enviado.');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorize(Permission::ManageMembers->value);

        $invitation->delete();

        return back();
    }

    public function show(Request $request, Invitation $invitation): Response
    {
        abort_unless($invitation->isPending() && $invitation->matchesToken((string) $request->string('token')), 404);

        return Inertia::render('Invitations/Accept', [
            'invitation' => ['email' => $invitation->email, 'role' => $invitation->role->label()],
            'action' => $request->fullUrl(),
        ]);
    }

    public function accept(Request $request, Invitation $invitation, AcceptInvitation $accept): RedirectResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:10'],
        ]);

        $accept->handle(
            $invitation,
            $request->string('token')->value(),
            $request->filled('name') ? $request->string('name')->value() : null,
            $request->string('password')->value(),
        );
        $request->session()->regenerate();

        return redirect('/');
    }
}
