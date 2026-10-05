<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\AuditLog;

function adminUrl(string $path = '/'): string
{
    return 'http://admin.tenantly.test/'.ltrim($path, '/');
}

beforeEach(function (): void {
    $this->admin = Admin::factory()->create(['email' => 'ops@tenantly.test', 'password' => 'senha-admin-123']);
});

it('autentica a equipe interna com guard próprio', function (): void {
    $this->get(adminUrl())->assertRedirect(adminUrl('login'));

    $this->post(adminUrl('login'), ['email' => 'ops@tenantly.test', 'password' => 'senha-admin-123'])->assertRedirect(rtrim(adminUrl(), '/'));
    $this->assertAuthenticatedAs($this->admin, 'admin');
});

it('usuário de tenant não acessa o painel', function (): void {
    $tenant = subscribedTenant();

    $this->actingAs(memberOf($tenant))->get(adminUrl())->assertRedirect(adminUrl('login'));
});

it('calcula MRR, churn e uso por plano com leitura auditada', function (): void {
    subscribedTenant('starter');
    subscribedTenant('pro');
    subscribedTenant('pro-anual');
    subscribedTenant('pro', SubscriptionStatus::Trialing);

    $this->actingAs($this->admin, 'admin')->get(adminUrl())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard')
            ->where('metrics.mrr_cents', 4900 + 14900 + intdiv(149000, 12))
            ->where('metrics.active_tenants', 4));

    expect(tenancy()->withoutTenancy('teste', fn () => AuditLog::query()->where('action', 'tenancy.bypass')->where('properties->reason', 'Painel central: métricas de assinaturas')->exists()))->toBeTrue();
});

it('suspende e reativa um tenant com motivo e auditoria no tenant', function (): void {
    $tenant = subscribedTenant();
    $user = memberOf($tenant);

    $this->actingAs($this->admin, 'admin')->post(adminUrl('tenants/'.$tenant->id.'/suspend'), ['reason' => 'curto'])->assertSessionHasErrors('reason');
    $this->actingAs($this->admin, 'admin')->post(adminUrl('tenants/'.$tenant->id.'/suspend'), ['reason' => 'Fraude confirmada no chamado #77'])->assertRedirect();

    $this->flushSession();
    $this->actingAs($user)->get(tenantUrl($tenant))->assertForbidden();

    inTenant($tenant, fn () => expect(AuditLog::query()->where('action', 'admin.tenant_suspended')->exists())->toBeTrue());
});

it('ajusta limites do tenant', function (): void {
    $tenant = subscribedTenant();

    $this->actingAs($this->admin, 'admin')
        ->post(adminUrl('tenants/'.$tenant->id.'/limits'), ['overrides' => ['users' => 100, 'projects' => null], 'reason' => 'Negociação comercial #12'])
        ->assertRedirect();

    expect($tenant->refresh()->limit_overrides)->toBe(['users' => 100]);
});

it('mostra o tenant sem ativar contexto de tenant no painel', function (): void {
    $tenant = subscribedTenant();
    memberOf($tenant, attributes: ['email' => 'dono@cliente.test']);

    $this->actingAs($this->admin, 'admin')->get(adminUrl('tenants/'.$tenant->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.0.email', 'dono@cliente.test')->where('tenant', fn ($t) => $t['id'] === $tenant->id));

    expect(tenancy()->check())->toBeFalse();
});

it('impersona a partir do painel gerando link de handoff no host do tenant', function (): void {
    $tenant = subscribedTenant(attributes: ['slug' => 'cliente']);
    $user = memberOf($tenant);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(adminUrl('tenants/'.$tenant->id.'/impersonate'), ['user_id' => $user->id, 'reason' => 'Chamado #555 erro no relatório']);

    expect($response->headers->get('Location') ?? $response->headers->get('X-Inertia-Location'))
        ->toStartWith('http://cliente.tenantly.test/auth/handoff?token=');
});
