<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    public function addTo(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Month => $date->addMonthNoOverflow(),
            self::Year => $date->addYearNoOverflow(),
        };
    }
}
