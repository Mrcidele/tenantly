<?php

declare(strict_types=1);

namespace App\Billing\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class InvalidWebhookSignature extends HttpException
{
    public function __construct()
    {
        parent::__construct(401, 'Assinatura do webhook inválida.');
    }
}
