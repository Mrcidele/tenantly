<?php

declare(strict_types=1);

use App\Auth\Impersonator;
use App\Enums\MembershipRole;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Impersonation;
use App\Models\Membership;
use App\Models\Tenant;

beforeEach(function (): void {
    $this->acme = subscribedTenant(attributes: ['slug' => 'acme', 'name' => 'Acme']);
    $this->globex = Tenant::factory()->create(['slug' => 'globex', 'name' => 'Globex']);
    $this->alice = memberOf($this->acme);
    inTenant($this->globex, fn () => Membership::factory()->create(['user_id' => $this->alice->id, 'role' => MembershipRole::Member]));
});

it('lista só as organizações do usuário', function (): void {
    Tenant::factory()->create(['name' => 'Initech']);

    $this->actingAs($this->alice)->get(tenantUrl($this->acme, 'organizations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Organizations/Index', false)->has('organizations', 2)
            ->where('organizations.0.name', 'Acme')->where('organizations.1.name', 'Globex'));
});

it('troca de organização com token de uso único no host de destino', function (): void {
    $response = $this->actingAs($this->alice)->post(tenantUrl($this->acme, 'organizations/'.$this->globex->id.'/switch'));
    $location = $response->headers->get('Location') ?? $response->headers->get('X-Inertia-Location');

    expect($location)->toStartWith('http://globex.tenantly.test/auth/handoff?token=');

    auth()->logout();
    $this->flushSession();

    $this->get($location)->assertRedirect('/');
    $this->assertAuthenticatedAs($this->alice);

    auth()->logout();
    $this->get($location)->assertForbidden(); // uso único
});

it('não troca para organização da qual não é membro', function (): void {
    $initech = Tenant::factory()->create();

    $this->actingAs($this->alice)->post(tenantUrl($this->acme, 'organizations/'.$initech->id.'/switch'))->assertNotFound();
});

it('impersona com motivo obrigatório, registro visível ao tenant e auditoria', function (): void {
    $admin = Admin::factory()->create();

    expect(fn () => app(Impersonator::class)->start($admin, $this->acme, $this->alice->id, 'curto'))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    $url = app(Impersonator::class)->start($admin, $this->acme, $this->alice->id, 'Chamado #1234: erro ao exportar');

    $this->get($url)->assertRedirect('/');
    $this->assertAuthenticatedAs($this->alice);
    expect(session('impersonator_id'))->toBe($admin->id);

    // Ações sensíveis bloqueadas e auditoria registrando o impersonador.
    $this->get(tenantUrl($this->acme, 'settings/api-tokens'))->assertForbidden();
    $this->post(tenantUrl($this->acme, 'projects'), ['name' => 'Feito pelo suporte'])->assertRedirect();

    $this->delete(tenantUrl($this->acme, 'impersonation'))->assertRedirect();
    $this->assertGuest();

    inTenant($this->acme, function () use ($admin): void {
        expect(Impersonation::query()->sole()->ended_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'impersonation.started')->sole()->properties['admin_id'])->toBe($admin->id)
            ->and(AuditLog::query()->where('action', 'impersonation.ended')->exists())->toBeTrue();
    });
});

it('não impersona usuário de outro tenant', function (): void {
    $bob = memberOf($this->globex);

    app(Impersonator::class)->start(Admin::factory()->create(), $this->acme, $bob->id, 'Chamado #999 tentativa indevida');
})->throws(Illuminate\Validation\ValidationException::class);
