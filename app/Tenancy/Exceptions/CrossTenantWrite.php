<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class CrossTenantWrite extends LogicException
{
    public static function foreignTenant(Model $model, string $tenantId): self
    {
        return new self(sprintf(
            'Tentativa de gravar [%s] no tenant [%s] a partir de outro tenant.',
            $model::class,
            $tenantId,
        ));
    }

    public static function immutable(Model $model): self
    {
        return new self(sprintf('O tenant_id de [%s] é imutável.', $model::class));
    }
}
