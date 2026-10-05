<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Lançada quando um model de tenant é consultado/gravado sem tenant ativo.
 * Falhar de forma barulhenta é intencional: "sem tenant" nunca significa "todos".
 */
final class MissingTenantContext extends LogicException
{
    public static function forQuery(Model $model): self
    {
        return new self(sprintf(
            'Consulta em [%s] sem tenant ativo. Use TenantContext::run() ou withoutTenancy() (auditado).',
            $model::class,
        ));
    }

    public static function forWrite(Model $model): self
    {
        return new self(sprintf('Gravação em [%s] sem tenant ativo.', $model::class));
    }

    public static function required(): self
    {
        return new self('Esta operação exige um tenant ativo.');
    }
}
