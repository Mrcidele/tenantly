<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Jobs\PurgeExpiredData;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $root = storage_path('framework/testing/disks/compliance');
    File::deleteDirectory($root);
    config()->set('filesystems.disks.local.root', $root);
    Storage::forgetDisk(['local', 'tenant']);
});

describe('auditoria', function (): void {
    it('registra quem fez o quê, com diff, no tenant', function (): void {
        $tenant = subscribedTenant();
        $owner = memberOf($tenant);

        $this->actingAs($owner)->post(tenantUrl($tenant, 'projects'), ['name' => 'Antigo']);
        $project = inTenant($tenant, fn () => Project::query()->sole());
        $this->actingAs($owner)->put(tenantUrl($tenant, 'projects/'.$project->id), ['name' => 'Novo']);

        inTenant($tenant, function () use ($owner): void {
            $log = AuditLog::query()->where('action', 'project.updated')->sole();
            expect($log->actor_id)->toBe($owner->id)
                ->and($log->properties)->toEqual(['before' => ['name' => 'Antigo'], 'after' => ['name' => 'Novo']])
                ->and($log->ip_address)->not->toBeNull();
        });
    });
});

describe('rate limit e enumeração', function (): void {
    it('limita requests por tenant + IP sem afetar outro tenant', function (): void {
        config()->set('tenancy.rate_limits.web_per_ip', 3);
        $a = subscribedTenant();
        $b = subscribedTenant();

        foreach (range(1, 3) as $i) {
            $this->get(tenantUrl($a, '.well-known/tenant'))->assertOk();
        }
        $this->get(tenantUrl($a, '.well-known/tenant'))->assertStatus(429);
        $this->get(tenantUrl($b, '.well-known/tenant'))->assertOk();
    });

    it('bloqueia varredura de subdomínios inexistentes por IP', function (): void {
        foreach (range(1, 20) as $i) {
            $this->get("http://nao-existe-{$i}.tenantly.test/")->assertNotFound();
        }

        $this->get('http://mais-um.tenantly.test/')->assertStatus(429);
    });
});

describe('LGPD', function (): void {
    it('exporta somente os dados do tenant', function (): void {
        Notification::fake();
        $tenant = subscribedTenant('pro');
        $other = subscribedTenant('pro');
        $owner = memberOf($tenant, attributes: ['email' => 'dono@a.test']);
        memberOf($other, attributes: ['email' => 'dono@b.test']);
        inTenant($tenant, fn () => Project::factory()->create(['name' => 'Projeto A']));
        inTenant($other, fn () => Project::factory()->create(['name' => 'Projeto B']));

        $this->actingAs($owner)->post(tenantUrl($tenant, 'settings/data/exports'))->assertRedirect();

        $export = inTenant($tenant, fn () => DataExport::query()->sole());
        expect($export->status)->toBe('ready');

        $zip = new ZipArchive;
        $zip->open(storage_path('framework/testing/disks/compliance/tenants/'.$tenant->id.'/'.$export->path));
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= $zip->getFromIndex($i);
        }

        expect($all)->toContain('Projeto A')->toContain('dono@a.test')
            ->not->toContain('Projeto B')->not->toContain('dono@b.test')->not->toContain($other->id);

        $this->actingAs($owner)->get(tenantUrl($tenant, 'settings/data/exports/'.$export->id))->assertRedirectContains('/files/exports/');
    });

    it('agenda a exclusão, bloqueia o acesso e purga após a retenção', function (): void {
        $tenant = subscribedTenant('pro');
        $other = subscribedTenant('pro');
        $owner = memberOf($tenant, attributes: ['password' => 'senha-do-dono-1']);
        $shared = memberOf($other);
        inTenant($tenant, fn () => Membership::factory()->create(['user_id' => $shared->id]));
        inTenant($tenant, fn () => Task::factory()->create());
        inTenant($other, fn () => Project::factory()->create());

        $this->actingAs($owner)->post(tenantUrl($tenant, 'settings/organization/delete'), ['password' => 'senha-do-dono-1', 'confirm' => 'errado'])
            ->assertSessionHasErrors('password');
        $this->actingAs($owner)->post(tenantUrl($tenant, 'settings/organization/delete'), ['password' => 'senha-do-dono-1', 'confirm' => $tenant->slug])
            ->assertRedirect();

        expect($tenant->refresh()->status)->toBe(TenantStatus::PendingDeletion);
        $this->get(tenantUrl($tenant, 'login'))->assertForbidden();

        PurgeExpiredData::dispatchSync();
        expect(Tenant::query()->find($tenant->id))->not->toBeNull();

        $this->travel(31)->days();
        PurgeExpiredData::dispatchSync();

        expect(Tenant::query()->find($tenant->id))->toBeNull();

        tenancy()->withoutTenancy('verificação do teste', function () use ($tenant, $owner, $shared, $other): void {
            expect(DB::connection('pgsql_admin')->table('projects')->where('tenant_id', $tenant->id)->count())->toBe(0)
                ->and(DB::connection('pgsql_admin')->table('users')->where('id', $owner->id)->exists())->toBeFalse()
                ->and(DB::connection('pgsql_admin')->table('users')->where('id', $shared->id)->exists())->toBeTrue()
                ->and(DB::connection('pgsql_admin')->table('projects')->where('tenant_id', $other->id)->count())->toBe(1)
                ->and(AuditLog::query()->where('action', 'tenant.purged')->exists())->toBeTrue();
        });
    });
});

describe('backup e restauração de um único tenant', function (): void {
    beforeEach(function (): void {
        config()->set('database.connections.restore_source', [...config('database.connections.migrator'), 'database' => 'tenantly_restore_test']);
        config()->set('tenancy.connections.unsynchronized', [...config('tenancy.connections.unsynchronized'), 'restore_source']);
        Artisan::call('migrate:fresh', ['--database' => 'restore_source', '--force' => true]);
    });

    it('restaura apenas o tenant pedido a partir do backup', function (): void {
        $tenant = subscribedTenant();
        $other = subscribedTenant();
        $owner = memberOf($tenant);
        inTenant($tenant, fn () => Project::factory()->create(['name' => 'Original']));
        inTenant($other, fn () => Project::factory()->create(['name' => 'Do vizinho']));

        // "Backup": copia o estado atual para o banco de restauração.
        $source = DB::connection('restore_source');
        foreach (['tenants', 'users', 'plans', 'plan_features', ...App\Tenancy\Database\TenantTables::ordered('migrator')] as $table) {
            foreach (DB::connection('migrator')->table($table)->get() as $row) {
                $source->table($table)->insert((array) $row);
            }
        }

        // Incidente: dados do tenant apagados/alterados depois do backup.
        inTenant($tenant, function (): void {
            Project::query()->delete();
            Project::factory()->create(['name' => 'Criado depois']);
        });
        inTenant($other, fn () => Project::query()->update(['name' => 'Vizinho alterado']));

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, '--source' => 'restore_source', '--force' => true])->assertSuccessful();

        inTenant($tenant, fn () => expect(Project::query()->pluck('name')->all())->toBe(['Original'])
            ->and(User::query()->whereKey($owner->id)->exists())->toBeTrue());
        inTenant($other, fn () => expect(Project::query()->pluck('name')->all())->toBe(['Vizinho alterado']));
    });
});
