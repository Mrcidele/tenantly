<?php

declare(strict_types=1);

use App\Enums\DatabaseStrategy;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Database\TenantDatabaseStrategyResolver;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $base = config('database.connections.pgsql');
    $database = 'tenantly_dedicated_test';

    config()->set('database.connections.tenant_enterprise', [...$base, 'database' => $database]);
    config()->set('database.connections.tenant_enterprise_migrator', [
        ...config('database.connections.migrator'),
        'database' => $database,
    ]);
    config()->set('tenancy.connections.unsynchronized', [
        ...config('tenancy.connections.unsynchronized'), 'tenant_enterprise_migrator',
    ]);

    $this->enterprise = Tenant::factory()->create();
    $this->enterprise->forceFill([
        'database_strategy' => DatabaseStrategy::Dedicated,
        'database_config' => ['connection' => 'tenant_enterprise', 'migrator_connection' => 'tenant_enterprise_migrator'],
    ])->save();

    app(TenantDatabaseStrategyResolver::class)->for($this->enterprise)->provision($this->enterprise);
    DB::connection('tenant_enterprise_migrator')->statement('TRUNCATE projects, tasks CASCADE');
});

it('grava os dados de domínio no banco dedicado do tenant', function (): void {
    $project = inTenant($this->enterprise, function (): Project {
        expect((new Project)->getConnectionName())->toBe('tenant_enterprise');

        return Project::query()->create(['name' => 'Enterprise']);
    });

    expect(DB::connection('tenant_enterprise_migrator')->table('projects')->pluck('id')->all())->toBe([$project->id])
        ->and(tenancy()->withoutTenancy('teste', fn () => DB::connection('pgsql_admin')->table('projects')->count()))->toBe(0);
});

it('mantém identidade e memberships no banco central', function (): void {
    inTenant($this->enterprise, function (): void {
        Membership::factory()->for(User::factory())->create();

        expect((new Membership)->getConnectionName())->toBe('pgsql')
            ->and(Membership::query()->count())->toBe(1);
    });
});

it('aplica RLS também no banco dedicado', function (): void {
    $other = Tenant::factory()->create();

    inTenant($this->enterprise, fn () => Project::query()->create(['name' => 'Enterprise']));

    tenancy()->set($other);
    expect(DB::connection('tenant_enterprise')->select('select id from projects'))->toBeEmpty();
});
