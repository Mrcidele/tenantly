<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Facades\DB;

it('resolve o tenant pelo subdomínio', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->get('http://acme.tenantly.test/.well-known/tenant')
        ->assertOk()
        ->assertJsonPath('tenant.id', $tenant->id);
});

it('responde 404 para subdomínio desconhecido', function (): void {
    $this->get('http://nao-existe.tenantly.test/')->assertNotFound();
});

it('não trata subdomínios aninhados como tenant', function (): void {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->get('http://x.acme.tenantly.test/')->assertNotFound();
});

it('resolve domínio customizado apenas quando verificado', function (): void {
    $tenant = Tenant::factory()->create();

    inTenant($tenant, function (): void {
        TenantDomain::factory()->create(['domain' => 'app.cliente.com']);
        TenantDomain::factory()->verified()->create(['domain' => 'portal.cliente.com']);
    });

    $this->get('http://app.cliente.com/')->assertNotFound();
    $this->get('http://portal.cliente.com/.well-known/tenant')->assertOk()->assertJsonPath('tenant.id', $tenant->id);
});

it('bloqueia tenants que não estão ativos', function (Tenant $tenant): void {
    $this->get(tenantUrl($tenant, '.well-known/tenant'))->assertForbidden();
})->with([
    'suspenso' => fn () => Tenant::factory()->suspended()->create(),
    'provisionando' => fn () => Tenant::factory()->provisioning()->create(),
]);

it('não expõe rotas de tenant no domínio central', function (): void {
    $this->get(centralUrl('/'))->assertOk()->assertInertia(fn ($page) => $page->where('tenant', null));
    $this->get(centralUrl('/.well-known/tenant'))->assertNotFound();
    $this->get(centralUrl('/login'))->assertNotFound();
});

it('guarda a resolução no cache e não consulta o banco na segunda request', function (): void {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->get('http://acme.tenantly.test/.well-known/tenant')->assertOk();

    DB::enableQueryLog();
    $this->get('http://acme.tenantly.test/.well-known/tenant')->assertOk();

    $tenantQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], '"tenants"'));
    expect($tenantQueries)->toBeEmpty();
});

it('invalida o cache quando o slug muda', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->get('http://acme.tenantly.test/.well-known/tenant')->assertOk();

    $tenant->update(['slug' => 'acme-novo']);

    $this->get('http://acme.tenantly.test/.well-known/tenant')->assertNotFound();
    $this->get('http://acme-novo.tenantly.test/.well-known/tenant')->assertOk();
});

it('invalida o cache negativo quando o tenant é criado', function (): void {
    $this->get('http://recem.tenantly.test/.well-known/tenant')->assertNotFound();

    Tenant::factory()->create(['slug' => 'recem']);

    $this->get('http://recem.tenantly.test/.well-known/tenant')->assertOk();
});
