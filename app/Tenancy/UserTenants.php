<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Concerns\TenantScope;

/**
 * Organizações de um usuário (seletor de organização). Remove o TenantScope
 * de forma controlada: a política RLS `member_self` só devolve memberships
 * cujo user_id é o app.user_id da sessão do banco.
 */
final readonly class UserTenants
{
    public function __construct(private TenantContext $context) {}

    /**
     * @return list<array{tenant: Tenant, role: MembershipRole}>
     */
    public function for(User $user): array
    {
        if ($this->context->userId() !== $user->id) {
            return [];
        }

        $memberships = Membership::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->get();

        $tenants = Tenant::query()
            ->whereIn('id', $memberships->pluck('tenant_id'))
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($memberships as $membership) {
            $tenant = $tenants->get($membership->tenant_id);

            if ($tenant instanceof Tenant && $tenant->isActive()) {
                $result[] = ['tenant' => $tenant, 'role' => $membership->role];
            }
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['tenant']->name, $b['tenant']->name));

        return $result;
    }

    public function belongsTo(User $user, Tenant $tenant): bool
    {
        foreach ($this->for($user) as $entry) {
            if ($entry['tenant']->id === $tenant->id) {
                return true;
            }
        }

        return false;
    }
}
