<?php

declare(strict_types=1);

namespace App\Billing\Data;

final readonly class GatewaySubscription
{
    public function __construct(public string $id) {}
}
