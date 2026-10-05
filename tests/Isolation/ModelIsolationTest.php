<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantModels;

/*
 * Percorre TODOS os models com BelongsToTenant. Um model novo entra aqui
 * automaticamente; se não tiver factory, o teste falha.
 */

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
});

/**
 * @param  class-string<Model>  $class
 */
function createFor(Tenant $tenant, string $class): Model
{
    expect(in_array(HasFactory::class, class_uses_recursive($class), true))
        ->toBeTrue("{$class} precisa de factory para entrar na varredura de isolamento.");

    return inTenant($tenant, fn (): Model => $class::factory()->create());
}

it('descobre models de tenant', function (): void {
    expect(TenantModels::tenantScoped())->not->toBeEmpty();
});

it('não deixa um tenant ler dados de outro', function (string $class): void {
    $mine = createFor($this->a, $class);
    $theirs = createFor($this->b, $class);

    inTenant($this->a, function () use ($class, $mine, $theirs): void {
        $ids = $class::query()->pluck((new $class)->getKeyName())->all();

        expect($ids)->toContain($mine->getKey())
            ->not->toContain($theirs->getKey())
            ->and($class::query()->find($theirs->getKey()))->toBeNull()
            ->and($class::query()->whereKey($theirs->getKey())->exists())->toBeFalse();
    });
})->with(fn () => TenantModels::tenantScoped());

it('não deixa um tenant editar nem apagar dados de outro', function (string $class): void {
    createFor($this->a, $class);
    $theirs = createFor($this->b, $class);
    $before = tenancy()->withoutTenancy('teste de isolamento', fn () => $theirs->newQuery()->whereKey($theirs->getKey())->toBase()->first());

    inTenant($this->a, function () use ($class, $theirs): void {
        expect($class::query()->whereKey($theirs->getKey())->update(['updated_at' => now()->addYear()]))->toBe(0)
            ->and($class::query()->whereKey($theirs->getKey())->delete())->toBe(0);
    });

    $after = tenancy()->withoutTenancy('teste de isolamento', fn () => $theirs->newQuery()->whereKey($theirs->getKey())->toBase()->first());

    expect($after)->toEqual($before);
})->with(fn () => array_values(array_filter(
    TenantModels::tenantScoped(),
    // A auditoria é somente-inserção (o model bloqueia update/delete).
    static fn (string $class): bool => (new $class)->usesTimestamps() && $class::UPDATED_AT !== null,
)));

it('é barrado pelo RLS mesmo sem o global scope', function (string $class): void {
    createFor($this->a, $class);
    $theirs = createFor($this->b, $class);
    $table = (new $class)->getTable();

    inTenant($this->a, function () use ($class, $theirs, $table): void {
        expect($class::query()->withoutGlobalScope(TenantScope::class)->find($theirs->getKey()))->toBeNull()
            ->and(DB::connection((new $class)->getConnectionName())->select("select * from \"{$table}\" where id = ?", [$theirs->getKey()]))->toBeEmpty()
            ->and(DB::connection((new $class)->getConnectionName())->update("update \"{$table}\" set tenant_id = ? where id = ?", [$this->a->id, $theirs->getKey()]))->toBe(0);
    });
})->with(fn () => TenantModels::tenantScoped());

it('não devolve nada ao banco sem tenant na sessão', function (string $class): void {
    createFor($this->a, $class);
    $table = (new $class)->getTable();

    expect(DB::select("select * from \"{$table}\""))->toBeEmpty();
})->with(fn () => TenantModels::tenantScoped());
