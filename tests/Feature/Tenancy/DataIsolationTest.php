<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Concerns\TenantScope;
use App\Tenancy\Exceptions\CrossTenantWrite;
use App\Tenancy\Exceptions\MissingTenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->acme = Tenant::factory()->create(['slug' => 'acme']);
    $this->globex = Tenant::factory()->create(['slug' => 'globex']);

    $this->acmeProject = inTenant($this->acme, fn () => Project::factory()->create(['name' => 'Projeto Acme']));
    $this->globexProject = inTenant($this->globex, fn () => Project::factory()->create(['name' => 'Projeto Globex']));
});

describe('camada 1: global scope do Eloquent', function (): void {
    it('filtra as queries pelo tenant ativo', function (): void {
        inTenant($this->acme, function (): void {
            expect(Project::query()->pluck('id')->all())->toBe([$this->acmeProject->id])
                ->and(Project::query()->find($this->globexProject->id))->toBeNull();
        });
    });

    it('preenche tenant_id ao criar', function (): void {
        $project = inTenant($this->acme, fn () => Project::query()->create(['name' => 'Novo']));

        expect($project->tenant_id)->toBe($this->acme->id);
    });

    it('lança exceção ao consultar sem tenant ativo em vez de devolver tudo', function (): void {
        Project::query()->get();
    })->throws(MissingTenantContext::class);

    it('lança exceção ao gravar sem tenant ativo', function (): void {
        Project::query()->create(['name' => 'Órfão']);
    })->throws(MissingTenantContext::class);

    it('impede criar registro apontando para outro tenant', function (): void {
        inTenant($this->acme, fn () => Project::query()->forceCreate(['name' => 'X', 'tenant_id' => $this->globex->id]));
    })->throws(CrossTenantWrite::class);

    it('torna tenant_id imutável', function (): void {
        inTenant($this->acme, function (): void {
            $project = Project::query()->firstOrFail();
            $project->forceFill(['tenant_id' => $this->globex->id])->save();
        });
    })->throws(CrossTenantWrite::class);

    it('não atualiza nem apaga em massa registros de outro tenant', function (): void {
        inTenant($this->acme, function (): void {
            expect(Project::query()->update(['name' => 'hack']))->toBe(1)
                ->and(Project::query()->delete())->toBe(1);
        });

        inTenant($this->globex, fn () => expect(Project::query()->value('name'))->toBe('Projeto Globex'));
    });

    it('restaura o contexto anterior após run()', function (): void {
        tenancy()->set($this->acme);

        inTenant($this->globex, fn () => expect(tenancy()->id())->toBe($this->globex->id));

        expect(tenancy()->id())->toBe($this->acme->id);
    });
});

describe('camada 2: Row Level Security do Postgres', function (): void {
    it('barra leitura de outro tenant mesmo sem o global scope', function (): void {
        inTenant($this->acme, function (): void {
            $ids = Project::query()->withoutGlobalScope(TenantScope::class)->pluck('id')->all();

            expect($ids)->toBe([$this->acmeProject->id]);
        });
    });

    it('barra SQL cru', function (): void {
        inTenant($this->acme, function (): void {
            expect(DB::select('select id from projects'))->toHaveCount(1)
                ->and(DB::select('select id from projects where id = ?', [$this->globexProject->id]))->toBeEmpty();
        });
    });

    it('não devolve nada sem tenant definido na sessão do banco', function (): void {
        expect(DB::select('select id from projects'))->toBeEmpty()
            ->and(DB::select('select id from tasks'))->toBeEmpty()
            ->and(DB::select('select id from memberships'))->toBeEmpty();
    });

    it('barra update/delete cru em outro tenant', function (): void {
        inTenant($this->acme, function (): void {
            expect(DB::update('update projects set name = ? where id = ?', ['hack', $this->globexProject->id]))->toBe(0)
                ->and(DB::delete('delete from projects where id = ?', [$this->globexProject->id]))->toBe(0);
        });

        inTenant($this->globex, fn () => expect(Project::query()->value('name'))->toBe('Projeto Globex'));
    });

    it('barra insert cru com tenant_id de outro tenant (WITH CHECK)', function (): void {
        inTenant($this->acme, fn () => DB::insert(
            'insert into projects (id, tenant_id, name, created_at, updated_at) values (?, ?, ?, now(), now())',
            [(string) Str::uuid7(), $this->globex->id, 'injetado'],
        ));
    })->throws(QueryException::class, 'row-level security');

    it('impede tarefa apontando para projeto de outro tenant (FK composta)', function (): void {
        inTenant($this->acme, fn () => Task::factory()->create(['project_id' => $this->globexProject->id]));
    })->throws(QueryException::class);

    it('reaplica o tenant quando um rollback desfaz o set_config', function (): void {
        tenancy()->set($this->acme);
        expect(DB::select('select id from projects'))->toHaveCount(1);

        DB::beginTransaction();
        tenancy()->set($this->globex);
        expect(DB::selectOne('select current_setting(\'app.tenant_id\') as t')->t)->toBe($this->globex->id);
        DB::rollBack();

        expect(DB::selectOne('select current_setting(\'app.tenant_id\') as t')->t)->toBe($this->globex->id);
    });

    it('roda a aplicação com papel sem BYPASSRLS e tabelas com FORCE RLS', function (): void {
        $role = DB::selectOne('select rolbypassrls from pg_roles where rolname = current_user');
        $table = DB::selectOne("select relrowsecurity, relforcerowsecurity from pg_class where relname = 'projects'");

        expect($role->rolbypassrls)->toBeFalse()
            ->and($table->relrowsecurity)->toBeTrue()
            ->and($table->relforcerowsecurity)->toBeTrue();
    });
});

describe('usuários e memberships', function (): void {
    beforeEach(function (): void {
        $this->alice = inTenant($this->acme, function (): User {
            $user = User::factory()->create();
            Membership::factory()->for($user)->create(['role' => MembershipRole::Owner]);

            return $user;
        });

        $this->bob = inTenant($this->globex, function (): User {
            $user = User::factory()->create();
            Membership::factory()->for($user)->create();

            return $user;
        });
    });

    it('só enxerga usuários membros do tenant ativo', function (): void {
        inTenant($this->acme, function (): void {
            expect(User::query()->pluck('id')->all())->toBe([$this->alice->id])
                ->and(User::query()->where('email', $this->bob->email)->exists())->toBeFalse();
        });
    });

    it('não enxerga nenhum usuário sem contexto', function (): void {
        expect(User::query()->count())->toBe(0);
    });

    it('permite ao usuário ver as próprias memberships em todos os tenants', function (): void {
        inTenant($this->globex, function (): void {
            Membership::factory()->create(['user_id' => $this->alice->id]);
        });

        tenancy()->setUser($this->alice->id);

        $tenants = DB::select('select tenant_id from memberships order by tenant_id');

        expect(array_column($tenants, 'tenant_id'))->toEqualCanonicalizing([$this->acme->id, $this->globex->id]);
    });
});

describe('withoutTenancy()', function (): void {
    it('permite consultas cross-tenant e grava auditoria com o motivo', function (): void {
        $count = tenancy()->withoutTenancy('relatório de MRR', fn () => Project::query()->count());

        expect($count)->toBe(2);

        $log = tenancy()->withoutTenancy('verificação do teste', fn () => AuditLog::query()
            ->where('action', 'tenancy.bypass')
            ->oldest()
            ->first());

        expect($log?->properties)->toBe(['reason' => 'relatório de MRR']);
    });

    it('exige um motivo', function (): void {
        tenancy()->withoutTenancy(' ', fn () => null);
    })->throws(InvalidArgumentException::class);

    it('usa a conexão com BYPASSRLS apenas dentro do callback', function (): void {
        tenancy()->withoutTenancy('teste', fn () => expect((new Project)->getConnectionName())->toBe('pgsql_admin'));

        expect((new Project)->getConnectionName())->toBe('pgsql');
    });
});
