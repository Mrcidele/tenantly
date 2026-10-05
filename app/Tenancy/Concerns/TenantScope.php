<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use App\Tenancy\Exceptions\MissingTenantContext;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $tenantId = $context->id() ?? throw MissingTenantContext::forQuery($model);

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
