<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resposta idêntica para "não existe" e "não pode ver", para não permitir
 * enumeração de subdomínios.
 */
final class TenantNotFound extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('Not Found');
    }
}
