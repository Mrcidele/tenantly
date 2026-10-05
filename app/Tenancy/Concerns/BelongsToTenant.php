<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use App\Models\Tenant;
use App\Tenancy\Contracts\StoresTenantData;
use App\Tenancy\Database\ConnectionRouter;
use App\Tenancy\Exceptions\CrossTenantWrite;
use App\Tenancy\Exceptions\MissingTenantContext;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primeira camada de isolamento (a segunda é o RLS do Postgres):
 * - global scope `tenant_id = <tenant ativo>` em toda query;
 * - `tenant_id` preenchido no `creating` e imutável depois;
 * - sem tenant ativo, consultas e gravações lançam MissingTenantContext.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(static function (Model $model): void {
            $context = app(TenantContext::class);
            $current = $context->id();
            $assigned = $model->getAttribute('tenant_id');

            if ($assigned === null) {
                if ($current === null) {
                    if (static::allowsCentralRecords()) {
                        return;
                    }

                    throw MissingTenantContext::forWrite($model);
                }

                $model->setAttribute('tenant_id', $current);

                return;
            }

            if (! $context->isBypassed() && $assigned !== $current) {
                throw CrossTenantWrite::foreignTenant($model, is_string($assigned) ? $assigned : '?');
            }
        });

        static::updating(static function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw CrossTenantWrite::immutable($model);
            }
        });
    }

    /**
     * Registros sem tenant (ex.: eventos de plataforma na auditoria).
     */
    public static function allowsCentralRecords(): bool
    {
        return false;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getConnectionName(): string
    {
        $router = app(ConnectionRouter::class);

        return $this instanceof StoresTenantData ? $router->tenantData() : $router->central();
    }
}
