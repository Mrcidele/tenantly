<?php

declare(strict_types=1);

use App\Models\Tenant;

beforeEach(function (): void {
    $this->acme = Tenant::factory()->create(['slug' => 'acme']);
    $this->globex = Tenant::factory()->create(['slug' => 'globex']);
    $this->alice = memberOf($this->acme, attributes: ['email' => 'alice@acme.test', 'password' => 'senha-secreta-1']);
});

it('autentica membros no host do próprio tenant', function (): void {
    $this->post(tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => 'senha-secreta-1'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($this->alice);
});

it('não autentica no host de outro tenant (a conta é invisível lá)', function (): void {
    $this->post(tenantUrl($this->globex, 'login'), ['email' => 'alice@acme.test', 'password' => 'senha-secreta-1'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('derruba sessão aberta em outro tenant', function (): void {
    $this->actingAs($this->alice)
        ->withSession(['tenant_id' => $this->globex->id])
        ->get(tenantUrl($this->acme))
        ->assertForbidden();
});

it('nega acesso a usuário autenticado que não é membro do tenant do host', function (): void {
    $this->actingAs($this->alice)->get(tenantUrl($this->globex))->assertForbidden();
});

it('exige desafio de 2FA quando habilitado', function (): void {
    // Sob RLS, o usuário só pode ser alterado no contexto de um tenant do qual é membro.
    inTenant($this->acme, fn () => $this->alice->forceFill([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save());

    $this->post(tenantUrl($this->acme, 'login'), ['email' => 'alice@acme.test', 'password' => 'senha-secreta-1'])
        ->assertRedirect(tenantUrl($this->acme, 'two-factor-challenge'));

    $this->assertGuest();

    $this->post(tenantUrl($this->acme, 'two-factor-challenge'), ['recovery_code' => 'codigo-1'])->assertRedirect('/');
    $this->assertAuthenticatedAs($this->alice);
});
