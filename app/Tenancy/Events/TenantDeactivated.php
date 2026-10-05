<?php

declare(strict_types=1);

namespace App\Tenancy\Events;

use App\Models\Tenant;

final readonly class TenantDeactivated
{
    public function __construct(public Tenant $tenant) {}
}
