<?php

declare(strict_types=1);

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantModels;

/*
 * Verifica o schema real do Postgres. Uma migration que crie tabela com
 * tenant_id sem RLS, ou uma tabela nova sem classificação, quebra o CI.
 */

/**
 * Tabelas centrais (sem tenant_id). Ao criar uma tabela nova sem tenant_id,
 * ela precisa ser adicionada aqui conscientemente.
 */
const CENTRAL_TABLES = [
    'admins', 'billing_webhook_events', 'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations',
    'features', 'plan_features', 'plans',
    'password_reset_tokens', 'sessions', 'tenants', 'users',
];

/**
 * Políticas além de tenant_isolation. Cada uma amplia a visibilidade e por
 * isso precisa estar listada (e revisada) aqui.
 */
const EXTRA_POLICIES = [
    'audit_logs' => ['central_insert'],
    'memberships' => ['member_self'],
    'tenant_domains' => ['public_lookup'],
    'users' => ['user_delete', 'user_signup', 'user_update', 'user_visibility'],
];

/**
 * @return list<string>
 */
function tablesWithTenantId(): array
{
    return array_column(DB::select(
        "select table_name from information_schema.columns
         where table_schema = 'public' and column_name = 'tenant_id'
         order by table_name"
    ), 'table_name');
}

it('força RLS com a política tenant_isolation em toda tabela com tenant_id', function (): void {
    $tables = tablesWithTenantId();
    expect($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        $class = DB::selectOne(
            "select relrowsecurity, relforcerowsecurity from pg_class where oid = ('public.' || quote_ident(?))::regclass",
            [$table],
        );

        expect($class->relrowsecurity)->toBeTrue("{$table}: RLS desabilitado")
            ->and($class->relforcerowsecurity)->toBeTrue("{$table}: RLS não está FORCE");

        $policy = DB::selectOne(
            "select cmd, qual, with_check from pg_policies where schemaname = 'public' and tablename = ? and policyname = 'tenant_isolation'",
            [$table],
        );

        expect($policy)->not->toBeNull("{$table}: sem política tenant_isolation")
            ->and($policy->cmd)->toBe('ALL')
            ->and($policy->qual)->toBe('(tenant_id = current_tenant_id())')
            ->and($policy->with_check)->toBe('(tenant_id = current_tenant_id())');
    }
});

it('só tem as políticas extras revisadas', function (): void {
    $rows = DB::select("select tablename, policyname from pg_policies where schemaname = 'public' and policyname <> 'tenant_isolation' order by tablename, policyname");

    $extra = [];
    foreach ($rows as $row) {
        $extra[$row->tablename][] = $row->policyname;
    }

    expect($extra)->toBe(EXTRA_POLICIES);
});

it('não tem políticas permissivas para tabelas sem tenant_id fora da lista', function (): void {
    $rls = array_column(DB::select(
        "select relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
         where n.nspname = 'public' and c.relkind = 'r' and c.relrowsecurity order by relname"
    ), 'relname');

    expect(array_values(array_diff($rls, tablesWithTenantId())))->toBe(['users']);
});

it('classifica toda tabela como central ou de tenant', function (): void {
    $all = array_column(DB::select("select tablename from pg_tables where schemaname = 'public' order by tablename"), 'tablename');
    $unclassified = array_values(array_diff($all, tablesWithTenantId(), CENTRAL_TABLES));

    expect($unclassified)->toBe([], 'Tabelas sem tenant_id e fora de CENTRAL_TABLES: '.implode(', ', $unclassified));
});

it('usa BelongsToTenant em todo model cuja tabela tem tenant_id', function (): void {
    $tenantTables = tablesWithTenantId();

    foreach (TenantModels::all() as $class) {
        $model = new $class;

        if (in_array($model->getTable(), $tenantTables, true)) {
            expect(in_array(BelongsToTenant::class, class_uses_recursive($class), true))
                ->toBeTrue("{$class} usa a tabela {$model->getTable()} (com tenant_id) mas não usa BelongsToTenant");
        }
    }
});

it('não concede BYPASSRLS nem superusuário ao papel da aplicação', function (): void {
    $role = DB::selectOne('select rolsuper, rolbypassrls, rolcreaterole, rolcreatedb from pg_roles where rolname = current_user');

    expect($role->rolsuper)->toBeFalse()
        ->and($role->rolbypassrls)->toBeFalse()
        ->and($role->rolcreaterole)->toBeFalse()
        ->and($role->rolcreatedb)->toBeFalse();
});

it('não permite que o papel da aplicação altere o schema ou as políticas', function (): void {
    DB::statement('alter table projects disable row level security');
})->throws(Illuminate\Database\QueryException::class, 'must be owner');
