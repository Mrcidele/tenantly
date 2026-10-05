<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use App\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TenantInactive extends HttpException
{
    public function __construct(public readonly Tenant $tenant)
    {
        parent::__construct(403, 'Esta organização não está disponível no momento.');
    }
}
