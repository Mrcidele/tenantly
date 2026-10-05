<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Entitlements\UsageMeter;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Agendado: grava os contadores do Redis em usage_records (por tenant, sob RLS). */
final class PersistUsageCounters implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(UsageMeter $meter, TenantContext $context): void
    {
        $byTenant = [];

        foreach ($meter->all() as $counter) {
            $byTenant[$counter['tenant_id']][] = $counter;
        }

        foreach ($byTenant as $tenantId => $counters) {
            $tenant = Tenant::query()->find($tenantId);

            if ($tenant === null) {
                continue;
            }

            $context->run($tenant, function () use ($counters): void {
                foreach ($counters as $counter) {
                    UsageRecord::query()->updateOrCreate(
                        ['metric' => $counter['metric']->value, 'period' => $counter['period']],
                        ['value' => $counter['value']],
                    );
                }
            });
        }
    }
}
