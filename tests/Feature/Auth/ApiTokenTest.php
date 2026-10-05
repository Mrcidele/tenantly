<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Project;
use App\Models\Tenant;

beforeEach(function (): void {
    $this->acme = Tenant::factory()->create(['slug' => 'acme']);
    $this->globex = Tenant::factory()->create(['slug' => 'globex']);
    $this->alice = memberOf($this->acme);
    inTenant($this->acme, fn () => Project::factory()->create(['name' => 'Acme API']));
    inTenant($this->globex, fn () => Project::factory()->create(['name' => 'Globex API']));
    $this->token = inTenant($this->acme, fn () => $this->alice->createToken('ci', ['projects.view', 'projects.manage'])->plainTextToken);
});

it('autentica a API com token do tenant e o header X-Tenant', function (): void {
    $this->withToken($this->token)->withHeader('X-Tenant', 'acme')
        ->getJson('http://api.tenantly.test/api/v1/projects')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Acme API')
        ->assertJsonCount(1, 'data');
});

it('rejeita o token quando o header aponta para outro tenant', function (): void {
    $this->withToken($this->token)->withHeader('X-Tenant', $this->globex->id)
        ->getJson('http://api.tenantly.test/api/v1/projects')
        ->assertUnauthorized();
});

it('rejeita o token no subdomínio de outro tenant', function (): void {
    $this->withToken($this->token)->getJson(tenantUrl($this->globex, 'api/v1/projects'))->assertUnauthorized();
});

it('respeita as habilidades do token e o papel atual do usuário', function (): void {
    $readOnly = inTenant($this->acme, fn () => $this->alice->createToken('ro', ['projects.view'])->plainTextToken);

    $this->withToken($readOnly)->withHeader('X-Tenant', 'acme')
        ->postJson('http://api.tenantly.test/api/v1/projects', ['name' => 'X'])
        ->assertForbidden();

    inTenant($this->acme, fn () => $this->alice->membership()->update(['role' => MembershipRole::Viewer]));

    $this->withToken($this->token)->withHeader('X-Tenant', 'acme')
        ->postJson('http://api.tenantly.test/api/v1/projects', ['name' => 'X'])
        ->assertForbidden();
});

it('não cria token com permissões além do papel', function (): void {
    $member = memberOf($this->acme, MembershipRole::Member);

    $this->actingAs($member)->get(tenantUrl($this->acme, 'settings/api-tokens'))->assertForbidden();
});
