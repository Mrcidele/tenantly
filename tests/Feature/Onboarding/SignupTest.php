<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => (new Database\Seeders\PlanSeeder)->run());

function signupPayload(array $overrides = []): array
{
    return [
        'organization' => 'Acme Ltda',
        'subdomain' => 'acme',
        'name' => 'Alice',
        'email' => 'alice@acme.test',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
        'terms' => '1',
        ...$overrides,
    ];
}

it('valida o subdomínio', function (string $subdomain): void {
    Tenant::factory()->create(['slug' => 'ocupado']);

    $this->post(centralUrl('signup'), signupPayload(['subdomain' => $subdomain]))->assertSessionHasErrors('subdomain');

    expect(Tenant::query()->count())->toBe(1);
})->with(['www', 'admin', 'api', 'a', 'com espaço', '-acme', 'acme-', 'ac--me', 'ACME_x', 'ocupado', str_repeat('a', 64)]);

it('cria tenant, dono e dados iniciais e provisiona de forma assíncrona', function (): void {
    Queue::fake();

    $this->post(centralUrl('signup'), signupPayload())->assertRedirect();

    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    expect($tenant->status)->toBe(TenantStatus::Provisioning);
    Queue::assertPushed(ProvisionTenant::class, fn (ProvisionTenant $job): bool => $job->tenantId === $tenant->id);

    (new ProvisionTenant($tenant->id))->handle(...array_map(app(...), [
        App\Tenancy\TenantContext::class, App\Tenancy\Database\TenantDatabaseStrategyResolver::class,
        App\Tenancy\Filesystem\TenantFiles::class, App\Onboarding\TenantSeeder::class,
    ]));

    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and(array_keys($tenant->provisioning['steps']))->toEqualCanonicalizing(array_keys(ProvisionTenant::STEPS));

    inTenant($tenant, function (): void {
        $owner = User::query()->where('email', 'alice@acme.test')->sole();
        expect($owner->membership()?->role)->toBe(MembershipRole::Owner)
            ->and(Project::query()->count())->toBe(1);
    });
});

it('é idempotente: reexecutar o provisionamento não duplica nada', function (): void {
    $this->post(centralUrl('signup'), signupPayload())->assertRedirect();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();

    $tenant->forceFill(['status' => TenantStatus::Provisioning])->save();
    ProvisionTenant::dispatchSync($tenant->id);
    ProvisionTenant::dispatchSync($tenant->id);

    expect(inTenant($tenant, fn () => Project::query()->count()))->toBe(1)
        ->and($tenant->refresh()->status)->toBe(TenantStatus::Active);
});

it('mostra o status apenas para a sessão que fez o cadastro e entrega o login no host do tenant', function (): void {
    $this->post(centralUrl('signup'), signupPayload());
    $tenant = Tenant::query()->where('slug', 'acme')->sole();

    $this->getJson(centralUrl('signup/'.$tenant->id.'/status'))->assertOk()->assertJson(['ready' => true]);

    $response = $this->post(centralUrl('signup/'.$tenant->id.'/continue'));
    expect($response->headers->get('Location') ?? $response->headers->get('X-Inertia-Location'))
        ->toStartWith('http://acme.tenantly.test/auth/handoff?token=');

    $this->flushSession();
    $this->getJson(centralUrl('signup/'.$tenant->id.'/status'))->assertNotFound();
});

it('recusa e-mail já cadastrado sem deixar tenant órfão', function (): void {
    $globex = Tenant::factory()->create();
    memberOf($globex, attributes: ['email' => 'alice@acme.test']);

    $this->post(centralUrl('signup'), signupPayload())->assertSessionHasErrors('email');

    expect(Tenant::query()->where('slug', 'acme')->exists())->toBeFalse();
});

it('informa disponibilidade de subdomínio', function (): void {
    Tenant::factory()->create(['slug' => 'ocupado']);

    $this->getJson(centralUrl('signup/check-subdomain?subdomain=livre'))->assertJson(['available' => true]);
    $this->getJson(centralUrl('signup/check-subdomain?subdomain=ocupado'))->assertJson(['available' => false]);
});
