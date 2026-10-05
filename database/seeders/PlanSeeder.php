<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['starter', 'Starter', 4900, BillingInterval::Month, 1, [
                Limit::Users->value => 3, Limit::Projects->value => 10, Limit::StorageMb->value => 1024,
                Limit::ApiCallsPerMonth->value => 10_000, Feature::ApiAccess->value => true,
            ]],
            ['pro', 'Pro', 14900, BillingInterval::Month, 2, [
                Limit::Users->value => 20, Limit::Projects->value => -1, Limit::StorageMb->value => 10_240,
                Limit::ApiCallsPerMonth->value => 100_000, Feature::ApiAccess->value => true,
                Feature::CustomDomains->value => true, Feature::AuditLog->value => true, Feature::DataExport->value => true,
            ]],
            ['pro-anual', 'Pro (anual)', 149000, BillingInterval::Year, 3, [
                Limit::Users->value => 20, Limit::Projects->value => -1, Limit::StorageMb->value => 10_240,
                Limit::ApiCallsPerMonth->value => 100_000, Feature::ApiAccess->value => true,
                Feature::CustomDomains->value => true, Feature::AuditLog->value => true, Feature::DataExport->value => true,
            ]],
            ['enterprise', 'Enterprise', 99900, BillingInterval::Month, 4, [
                Limit::Users->value => -1, Limit::Projects->value => -1, Limit::StorageMb->value => -1,
                Limit::ApiCallsPerMonth->value => -1, Feature::ApiAccess->value => true, Feature::CustomDomains->value => true,
                Feature::AuditLog->value => true, Feature::DataExport->value => true, Feature::PrioritySupport->value => true,
            ]],
        ];

        foreach ($plans as [$code, $name, $price, $interval, $sort, $features]) {
            $plan = Plan::query()->updateOrCreate(['code' => $code], [
                'name' => $name, 'price_cents' => $price, 'interval' => $interval, 'sort' => $sort, 'trial_days' => 14,
            ]);

            foreach ($features as $feature => $value) {
                $plan->features()->updateOrCreate(['feature' => $feature], ['value' => $value === true ? 'true' : (string) $value]);
            }
        }
    }
}
