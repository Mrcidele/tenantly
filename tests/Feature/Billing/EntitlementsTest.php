<?php

declare(strict_types=1);

use App\Entitlements\Entitlements;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\MembershipRole;
use App\Models\Project;
use Illuminate\Support\Facades\Mail;
use Laravel\Pennant\Feature as Pennant;

it('lê features e limites do plano do tenant', function (): void {
    $starter = subscribedTenant('starter');
    $pro = subscribedTenant('pro');

    inTenant($starter, function (): void {
        $e = app(Entitlements::class);
        expect($e->can(Feature::CustomDomains))->toBeFalse()
            ->and($e->limit(Limit::Users))->toBe(3)
            ->and($e->limit(Limit::Projects))->toBe(10);
    });

    inTenant($pro, function (): void {
        $e = app(Entitlements::class);
        expect($e->can(Feature::CustomDomains))->toBeTrue()
            ->and($e->limit(Limit::Projects))->toBeNull();
    });
});

it('aplica ajustes manuais de limite do tenant', function (): void {
    $tenant = subscribedTenant('starter');
    $tenant->forceFill(['limit_overrides' => ['users' => 50, 'custom_domains' => true]])->save();

    inTenant($tenant->refresh(), function (): void {
        $e = app(Entitlements::class);
        expect($e->limit(Limit::Users))->toBe(50)->and($e->can(Feature::CustomDomains))->toBeTrue();
    });
});

it('bloqueia convites acima do limite de usuários', function (): void {
    Mail::fake();
    $tenant = subscribedTenant('starter');
    $owner = memberOf($tenant);
    memberOf($tenant, MembershipRole::Member);

    $this->actingAs($owner)->post(tenantUrl($tenant, 'invitations'), ['email' => 'c@x.com', 'role' => 'member'])->assertRedirect();
    $this->actingAs($owner)->post(tenantUrl($tenant, 'invitations'), ['email' => 'd@x.com', 'role' => 'member'])->assertStatus(402);
});

it('bloqueia projetos acima do limite', function (): void {
    $tenant = subscribedTenant('starter');
    $owner = memberOf($tenant);
    inTenant($tenant, fn () => Project::factory()->count(10)->create());

    $this->actingAs($owner)->post(tenantUrl($tenant, 'projects'), ['name' => 'Décimo primeiro'])->assertStatus(402);
});

it('mede chamadas de API por tenant e corta ao atingir a cota', function (): void {
    $tenant = subscribedTenant('starter');
    $tenant->forceFill(['limit_overrides' => ['api_calls_per_month' => 2]])->save();
    $other = subscribedTenant('starter');
    $owner = memberOf($tenant);
    $token = inTenant($tenant, fn () => $owner->createToken('t', ['projects.view'])->plainTextToken);

    $call = fn () => $this->withToken($token)->getJson(tenantUrl($tenant, 'api/v1/projects'));

    $call()->assertOk()->assertHeader('X-Quota-Remaining', '1');
    $call()->assertOk()->assertHeader('X-Quota-Remaining', '0');
    $call()->assertStatus(429);

    inTenant($other, fn () => expect(app(Entitlements::class)->usage(Limit::ApiCallsPerMonth))->toBe(0));
});

it('persiste os contadores do Redis por tenant', function (): void {
    $tenant = subscribedTenant('starter');
    app(App\Entitlements\UsageMeter::class)->increment($tenant->id, Limit::ApiCallsPerMonth, 7);

    App\Jobs\PersistUsageCounters::dispatchSync();

    inTenant($tenant, fn () => expect(App\Models\UsageRecord::query()->where('metric', 'api_calls_per_month')->value('value'))->toBe(7));
});

it('exige o recurso no plano para o log de auditoria', function (): void {
    $starter = subscribedTenant('starter');
    $pro = subscribedTenant('pro');

    $this->actingAs(memberOf($starter))->get(tenantUrl($starter, 'audit'))->assertStatus(402);
    $this->flushSession();
    $this->actingAs(memberOf($pro))->get(tenantUrl($pro, 'audit'))->assertOk();
});

it('resolve feature flags do Pennant por tenant', function (): void {
    $a = subscribedTenant();
    $b = subscribedTenant();

    inTenant($a, fn () => Pennant::activate('new-dashboard'));

    inTenant($a, fn () => expect(Pennant::active('new-dashboard'))->toBeTrue());
    inTenant($b, fn () => expect(Pennant::active('new-dashboard'))->toBeFalse());
});
