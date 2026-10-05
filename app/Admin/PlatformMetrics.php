<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/** Métricas da plataforma. Leituras cross-tenant sempre via withoutTenancy (auditado). */
final readonly class PlatformMetrics
{
    public function __construct(private TenantContext $context) {}

    /**
     * @return array{mrr_cents: int, active_tenants: int, churn_rate: float, by_plan: list<array{plan: string, tenants: int, mrr_cents: int}>, by_status: array<string, int>}
     */
    public function summary(): array
    {
        /** @var list<array{plan_id: string, status: string, canceled_at: string|null, created_at: string|null}> $rows */
        $rows = $this->context->withoutTenancy('Painel central: métricas de assinaturas', fn (): array => Subscription::query()
            ->get(['plan_id', 'status', 'canceled_at', 'created_at'])
            ->map(static fn (Subscription $s): array => [
                'plan_id' => $s->plan_id,
                'status' => $s->status->value,
                'canceled_at' => $s->canceled_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
            ])->all());

        $plans = Plan::query()->get()->keyBy('id');
        $paying = [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value];
        $byPlan = [];
        $byStatus = [];
        $mrr = 0;

        foreach ($rows as $row) {
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;
            $plan = $plans->get($row['plan_id']);

            if (! $plan instanceof Plan) {
                continue;
            }

            $byPlan[$plan->code] ??= ['plan' => $plan->name, 'tenants' => 0, 'mrr_cents' => 0];
            $byPlan[$plan->code]['tenants']++;

            if (in_array($row['status'], $paying, true)) {
                $monthly = self::monthly($plan);
                $mrr += $monthly;
                $byPlan[$plan->code]['mrr_cents'] += $monthly;
            }
        }

        return [
            'mrr_cents' => $mrr,
            'active_tenants' => Tenant::query()->where('status', TenantStatus::Active)->count(),
            'churn_rate' => $this->churn($rows, CarbonImmutable::now()->subDays(30)),
            'by_plan' => array_values($byPlan),
            'by_status' => $byStatus,
        ];
    }

    public static function monthly(Plan $plan): int
    {
        return $plan->interval === BillingInterval::Year ? intdiv($plan->price_cents, 12) : $plan->price_cents;
    }

    /**
     * Cancelamentos nos últimos 30 dias ÷ assinaturas existentes no início do período.
     *
     * @param  list<array{plan_id: string, status: string, canceled_at: string|null, created_at: string|null}>  $rows
     */
    private function churn(array $rows, CarbonImmutable $since): float
    {
        $base = 0;
        $canceled = 0;

        foreach ($rows as $row) {
            $created = $row['created_at'] !== null ? CarbonImmutable::parse($row['created_at']) : null;
            $canceledAt = $row['canceled_at'] !== null ? CarbonImmutable::parse($row['canceled_at']) : null;

            if ($created !== null && $created->lessThan($since) && ($canceledAt === null || $canceledAt->greaterThanOrEqualTo($since))) {
                $base++;

                if ($canceledAt !== null) {
                    $canceled++;
                }
            }
        }

        return $base === 0 ? 0.0 : round($canceled / $base * 100, 2);
    }
}
