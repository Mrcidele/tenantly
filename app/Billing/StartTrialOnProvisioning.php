<?php

declare(strict_types=1);

namespace App\Billing;

use App\Events\TenantProvisioningBilling;

final readonly class StartTrialOnProvisioning
{
    public function __construct(private BillingService $billing) {}

    public function handle(TenantProvisioningBilling $event): void
    {
        $this->billing->startTrial();
    }
}
