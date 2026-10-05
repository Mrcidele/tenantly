<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Membership;

beforeEach(function (): void {
    $this->acme = subscribedTenant();
});

it('aplica permissões por papel', function (MembershipRole $role, int $status): void {
    $user = memberOf($this->acme, $role);

    $this->actingAs($user)->post(tenantUrl($this->acme, 'projects'), ['name' => 'Novo'])->assertStatus($status);
})->with([
    'dono' => [MembershipRole::Owner, 302],
    'membro' => [MembershipRole::Member, 302],
    'leitor' => [MembershipRole::Viewer, 403],
]);

it('o mesmo usuário tem papéis diferentes em cada tenant', function (): void {
    $globex = subscribedTenant();
    $user = memberOf($this->acme, MembershipRole::Viewer);
    inTenant($globex, fn () => Membership::factory()->create(['user_id' => $user->id, 'role' => MembershipRole::Owner]));

    $this->actingAs($user)->post(tenantUrl($this->acme, 'projects'), ['name' => 'X'])->assertForbidden();

    // Navegadores mantêm uma sessão por host; o teste simula isso.
    $this->flushSession();
    $this->actingAs($user)->post(tenantUrl($globex, 'projects'), ['name' => 'X'])->assertRedirect();
});

it('impede rebaixar o último dono', function (): void {
    $owner = memberOf($this->acme);
    $membership = inTenant($this->acme, fn () => $owner->membership());

    $this->actingAs($owner)
        ->patch(tenantUrl($this->acme, 'members/'.$membership->id), ['role' => 'member'])
        ->assertSessionHasErrors('role');
});

it('administrador não promove ninguém a dono', function (): void {
    $admin = memberOf($this->acme, MembershipRole::Admin);
    $member = memberOf($this->acme, MembershipRole::Member);
    $membership = inTenant($this->acme, fn () => Membership::query()->where('user_id', $member->id)->first());

    $this->actingAs($admin)
        ->patch(tenantUrl($this->acme, 'members/'.$membership->id), ['role' => 'owner'])
        ->assertSessionHasErrors('role');
});
