<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Enums\Limit;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class LimitExceeded extends HttpException
{
    public function __construct(public readonly Limit $limit, public readonly int $max)
    {
        parent::__construct(402, sprintf('Limite do plano atingido: %s (máximo %d). Faça upgrade para continuar.', $limit->label(), $max));
    }
}
