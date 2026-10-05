<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Members\ManageMembership;
use App\Enums\MembershipRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class MemberController extends Controller
{
    public function index(): Response
    {
        $this->authorize(Permission::ViewMembers->value);

        return Inertia::render('Members/Index', [
            'members' => Membership::query()->with('user:id,name,email')->oldest()->get()
                ->map(static fn (Membership $m): array => [
                    'id' => $m->id,
                    'role' => $m->role->value,
                    'user' => ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email],
                ]),
            'invitations' => Invitation::query()->whereNull('accepted_at')->latest()->get(['id', 'email', 'role', 'expires_at']),
            'roles' => array_map(static fn (MembershipRole $r): array => ['value' => $r->value, 'label' => $r->label()], MembershipRole::cases()),
        ]);
    }

    public function update(Request $request, Membership $member, ManageMembership $manage): RedirectResponse
    {
        $this->authorize(Permission::ManageMembers->value);

        $request->validate(['role' => ['required', Rule::enum(MembershipRole::class)]]);
        $role = $request->enum('role', MembershipRole::class);
        assert($role instanceof MembershipRole);
        $user = $request->user();
        assert($user instanceof User);

        $manage->changeRole($user, $member, $role);

        return back();
    }

    public function destroy(Request $request, Membership $member, ManageMembership $manage): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        if ($member->user_id !== $user->id) {
            $this->authorize(Permission::ManageMembers->value);
        }

        $manage->remove($user, $member);

        return back();
    }
}
