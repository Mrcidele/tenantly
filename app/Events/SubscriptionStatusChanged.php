<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

final readonly class SubscriptionStatusChanged
{
    public function __construct(
        public Subscription $subscription,
        public SubscriptionStatus $from,
        public SubscriptionStatus $to,
    ) {}
}
