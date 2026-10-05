<?php

declare(strict_types=1);

namespace App\Billing\Data;

use Carbon\CarbonImmutable;

final readonly class Proration
{
    public function __construct(
        public int $creditCents,
        public int $chargeCents,
        public CarbonImmutable $periodStartsAt,
        public CarbonImmutable $periodEndsAt,
    ) {}

    /** Positivo: cobrar agora. Negativo: crédito para o próximo ciclo. */
    public function netCents(): int
    {
        return $this->chargeCents - $this->creditCents;
    }
}
