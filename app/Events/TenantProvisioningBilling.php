<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;

/** Evento disparado no passo "billing" do provisionamento (a cobrança escuta). */
final readonly class TenantProvisioningBilling
{
    public function __construct(public Tenant $tenant) {}
}
