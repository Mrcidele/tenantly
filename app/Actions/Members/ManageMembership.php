<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Audit\AuditLogger;
use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class ManageMembership
{
    public function __construct(private AuditLogger $audit) {}

    public function changeRole(User $actor, Membership $membership, MembershipRole $role): Membership
    {
        $this->authorize($actor, $membership, $role);
        $this->ensureOwnerRemains($membership, $role);

        $from = $membership->role;
        $membership->update(['role' => $role]);

        $this->audit->record('member.role_changed', ['from' => $from->value, 'to' => $role->value], $membership);

        return $membership;
    }

    public function remove(User $actor, Membership $membership): void
    {
        if ($membership->user_id !== $actor->id) {
            $this->authorize($actor, $membership, $membership->role);
        }

        $this->ensureOwnerRemains($membership, null);

        $membership->delete();

        $this->audit->record('member.removed', ['user_id' => $membership->user_id], $membership);
    }

    private function authorize(User $actor, Membership $target, MembershipRole $newRole): void
    {
        $assignable = $actor->membership()?->role->assignable() ?? [];

        if (! in_array($target->role, $assignable, true) || ! in_array($newRole, $assignable, true)) {
            throw ValidationException::withMessages(['role' => 'Você não pode alterar este membro.']);
        }
    }

    private function ensureOwnerRemains(Membership $membership, ?MembershipRole $newRole): void
    {
        if ($membership->role !== MembershipRole::Owner || $newRole === MembershipRole::Owner) {
            return;
        }

        if (Membership::query()->where('role', MembershipRole::Owner)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'A organização precisa de pelo menos um dono.']);
        }
    }
}
