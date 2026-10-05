<?php

declare(strict_types=1);

use App\Domains\DnsResolver;
use App\Jobs\VerifyCustomDomains;
use App\Models\TenantDomain;
use Tests\Support\FakeDns;

beforeEach(function (): void {
    $this->dns = new FakeDns;
    app()->instance(DnsResolver::class, $this->dns);
    config()->set('tenancy.cname_target', 'cname.tenantly.test');

    $this->tenant = subscribedTenant('pro', attributes: ['slug' => 'acme']);
    $this->owner = memberOf($this->tenant);
});

function pointDns(FakeDns $dns, TenantDomain $domain): void
{
    $dns->txt['_tenantly.'.$domain->domain] = [$domain->verification_token];
    $dns->cname[$domain->domain] = ['cname.tenantly.test'];
}

it('exige o recurso no plano', function (): void {
    $starter = subscribedTenant('starter');

    $this->actingAs(memberOf($starter))->get(tenantUrl($starter, 'settings/domains'))->assertStatus(402);
});

it('cadastra, verifica via DNS e passa a resolver o tenant pelo domínio', function (): void {
    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'settings/domains'), ['domain' => 'App.Cliente.com.'])->assertSessionHasNoErrors();

    $domain = inTenant($this->tenant, fn () => TenantDomain::query()->sole());
    expect($domain->domain)->toBe('app.cliente.com');

    $this->get('http://app.cliente.com/.well-known/tenant')->assertNotFound();

    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'settings/domains/'.$domain->id.'/verify'));
    expect(inTenant($this->tenant, fn () => $domain->refresh()->status->value))->toBe('failed');

    pointDns($this->dns, $domain);
    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'settings/domains/'.$domain->id.'/verify'));

    expect($domain->refresh()->isVerified())->toBeTrue();
    $this->get('http://app.cliente.com/.well-known/tenant')->assertOk()->assertJsonPath('tenant.id', $this->tenant->id);
});

it('exige TXT e CNAME', function (): void {
    $domain = inTenant($this->tenant, fn () => TenantDomain::factory()->create(['domain' => 'app.cliente.com']));
    $this->dns->cname['app.cliente.com'] = ['cname.tenantly.test'];

    inTenant($this->tenant, fn () => app(App\Domains\DomainVerifier::class)->verify($domain));

    // Domínio não verificado só é visível no contexto do próprio tenant (RLS).
    expect(inTenant($this->tenant, fn () => $domain->refresh()->failure_reason))->toContain('TXT');
});

it('define domínio principal e gera URLs nele', function (): void {
    $domain = inTenant($this->tenant, fn () => TenantDomain::factory()->create(['domain' => 'app.cliente.com']));
    pointDns($this->dns, $domain);
    inTenant($this->tenant, fn () => app(App\Domains\DomainVerifier::class)->verify($domain));

    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'settings/domains/'.$domain->id.'/primary'))->assertRedirect();

    expect($this->tenant->refresh()->primary_domain)->toBe('app.cliente.com')
        ->and($this->tenant->url('/x'))->toBe('http://app.cliente.com/x');
});

it('revalida periodicamente e remove o domínio quando o DNS deixa de apontar', function (): void {
    $domain = inTenant($this->tenant, fn () => TenantDomain::factory()->create(['domain' => 'app.cliente.com', 'is_primary' => true]));
    pointDns($this->dns, $domain);

    (new VerifyCustomDomains)->handle(tenancy(), app(App\Domains\DomainVerifier::class));
    expect($domain->refresh()->isVerified())->toBeTrue()
        ->and($this->tenant->refresh()->primary_domain)->toBe('app.cliente.com');

    $this->dns->cname = [];
    $this->travel(10)->minutes();
    (new VerifyCustomDomains(includeVerified: true))->handle(tenancy(), app(App\Domains\DomainVerifier::class));

    expect(inTenant($this->tenant, fn () => $domain->refresh()->isVerified()))->toBeFalse()
        ->and($this->tenant->refresh()->primary_domain)->toBeNull();
    $this->get('http://app.cliente.com/.well-known/tenant')->assertNotFound();
});

it('não permite cadastrar domínio de outro tenant nem do domínio central', function (): void {
    $other = subscribedTenant('pro');
    inTenant($other, fn () => TenantDomain::factory()->create(['domain' => 'app.outro.com']));

    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'settings/domains'), ['domain' => 'app.outro.com'])->assertSessionHasErrors('domain');
    $this->actingAs($this->owner)->post(tenantUrl($this->tenant, 'settings/domains'), ['domain' => 'x.tenantly.test'])->assertSessionHasErrors('domain');
});

it('autoriza TLS on-demand só para hosts conhecidos e só na rede interna', function (): void {
    $domain = inTenant($this->tenant, fn () => TenantDomain::factory()->verified()->create(['domain' => 'app.cliente.com']));

    $ask = fn (string $host, string $ip = '10.0.0.5') => $this->call('GET', centralUrl('internal/tls/ask?domain='.$host), [], [], [], ['REMOTE_ADDR' => $ip])->status();

    expect($ask('acme.tenantly.test'))->toBe(200)
        ->and($ask('app.cliente.com'))->toBe(200)
        ->and($ask('nao-existe.tenantly.test'))->toBe(404)
        ->and($ask('qualquer.com'))->toBe(404)
        ->and($ask('acme.tenantly.test', '8.8.8.8'))->toBe(404);
});
