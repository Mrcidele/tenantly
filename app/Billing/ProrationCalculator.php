<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Data\Proration;
use App\Models\Plan;
use Carbon\CarbonImmutable;

/**
 * Proração por tempo restante do ciclo.
 * - Mesmo intervalo: crédito do plano atual e cobrança do novo, ambos
 *   proporcionais ao restante do ciclo; o ciclo não muda.
 * - Intervalo diferente (mensal ↔ anual): novo ciclo começa agora; cobra o
 *   valor cheio do novo plano menos o crédito do restante do atual.
 */
final class ProrationCalculator
{
    public function calculate(Plan $from, Plan $to, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, CarbonImmutable $now): Proration
    {
        $total = max(1, $periodStart->diffInSeconds($periodEnd));
        $remaining = max(0, min($total, $now->diffInSeconds($periodEnd, false)));
        $ratio = $remaining / $total;

        $credit = (int) round($from->price_cents * $ratio);

        if ($from->interval === $to->interval) {
            return new Proration($credit, (int) round($to->price_cents * $ratio), $periodStart, $periodEnd);
        }

        return new Proration($credit, $to->price_cents, $now, $to->interval->addTo($now));
    }
}
