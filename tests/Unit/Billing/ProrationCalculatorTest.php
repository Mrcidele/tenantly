<?php

declare(strict_types=1);

use App\Billing\ProrationCalculator;
use App\Enums\BillingInterval;
use App\Models\Plan;
use Carbon\CarbonImmutable;

function plan(int $cents, BillingInterval $interval = BillingInterval::Month): Plan
{
    return (new Plan)->forceFill(['price_cents' => $cents, 'interval' => $interval]);
}

it('credita o restante do plano atual e cobra o restante do novo no mesmo ciclo', function (): void {
    $start = CarbonImmutable::parse('2026-10-01');
    $end = CarbonImmutable::parse('2026-10-31');
    $now = CarbonImmutable::parse('2026-10-16');

    $p = (new ProrationCalculator)->calculate(plan(4900), plan(14900), $start, $end, $now);

    expect($p->creditCents)->toBe(2450)
        ->and($p->chargeCents)->toBe(7450)
        ->and($p->netCents())->toBe(5000)
        ->and($p->periodEndsAt->equalTo($end))->toBeTrue();
});

it('gera crédito (valor negativo) no downgrade', function (): void {
    $p = (new ProrationCalculator)->calculate(plan(14900), plan(4900), CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), CarbonImmutable::parse('2026-10-16'));

    expect($p->netCents())->toBe(-5000);
});

it('reinicia o ciclo ao mudar de mensal para anual', function (): void {
    $now = CarbonImmutable::parse('2026-10-16');
    $p = (new ProrationCalculator)->calculate(plan(14900), plan(149000, BillingInterval::Year), CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), $now);

    expect($p->chargeCents)->toBe(149000)
        ->and($p->creditCents)->toBe(7450)
        ->and($p->periodStartsAt->equalTo($now))->toBeTrue()
        ->and($p->periodEndsAt->toDateString())->toBe('2027-10-16');
});

it('não credita nada após o fim do ciclo', function (): void {
    $p = (new ProrationCalculator)->calculate(plan(4900), plan(14900), CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), CarbonImmutable::parse('2026-11-05'));

    expect($p->creditCents)->toBe(0)->and($p->chargeCents)->toBe(0);
});
