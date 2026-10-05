<?php

declare(strict_types=1);

use App\Jobs\ExportTenantData;
use App\Models\Admin;
use App\Models\Tenant;
use App\Observability\TenantUsageRecorder;
use App\Tenancy\Octane\ResetTenancyState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Laravel\Pulse\Facades\Pulse;
use Symfony\Component\HttpFoundation\Response;

it('não deixa estado de tenant para a próxima request do worker (Octane)', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    // Simula um worker que terminou uma request sem limpar o contexto.
    tenancy()->set($tenant);
    Cache::put('k', 'do-tenant');

    (new ResetTenancyState)->handle((object) ['sandbox' => app()]);

    expect(tenancy()->check())->toBeFalse()
        ->and(Cache::get('k'))->toBeNull()
        ->and(Context::get('tenant_id'))->toBeNull()
        ->and(url('/x'))->not->toContain('acme')
        ->and(DB::selectOne("select current_setting('app.tenant_id') as t")->t)->toBe('');
});

it('verifica banco e Redis no health check', function (): void {
    $this->get(centralUrl('up'))->assertOk();
});

it('registra requests por tenant no Pulse (vizinho barulhento)', function (): void {
    config()->set('pulse.enabled', true);
    Pulse::startRecording();
    $tenant = Tenant::factory()->create();

    inTenant($tenant, fn () => app(TenantUsageRecorder::class)->record(CarbonImmutable::now()->subMilliseconds(120), Request::create('/'), new Response));
    Pulse::ingest();

    $entry = DB::table('pulse_entries')->where('type', 'tenant_request')->first();

    expect($entry?->key)->toBe($tenant->id)
        ->and($entry?->value)->toBeGreaterThanOrEqual(100);
});

it('envia jobs pesados de tenants grandes para a fila dedicada', function (): void {
    $big = Tenant::factory()->create();
    $big->forceFill(['queue' => 'tenants-large'])->save();
    $small = Tenant::factory()->create();

    expect(inTenant($big, fn () => (new ExportTenantData('x'))->queue))->toBe('tenants-large')
        ->and(inTenant($small, fn () => (new ExportTenantData('x'))->queue))->toBe('default');
});

it('restringe Horizon e Pulse à equipe interna no domínio do painel', function (): void {
    $this->get('http://admin.tenantly.test/horizon')->assertRedirect();
    $this->get('http://admin.tenantly.test/pulse')->assertRedirect();

    $this->actingAs(Admin::factory()->create(), 'admin')->get('http://admin.tenantly.test/horizon')->assertOk();
});

it('o admin move um tenant para a fila dedicada com auditoria', function (): void {
    $tenant = subscribedTenant();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post('http://admin.tenantly.test/tenants/'.$tenant->id.'/queue', ['queue' => 'tenants-large', 'reason' => 'Volume alto de exportações'])
        ->assertRedirect();

    expect($tenant->refresh()->queue)->toBe('tenants-large');
});
