<?php

declare(strict_types=1);

namespace App\Billing;

use App\Audit\AuditLogger;
use App\Entitlements\Entitlements;
use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionStatusChanged;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use LogicException;

/** Único ponto que altera o status de uma assinatura. */
final readonly class SubscriptionStateMachine
{
    public function __construct(
        private AuditLogger $audit,
        private Entitlements $entitlements,
    ) {}

    public function transition(Subscription $subscription, SubscriptionStatus $to, string $reason): Subscription
    {
        $from = $subscription->status;

        if ($from === $to) {
            return $subscription;
        }

        if (! $from->canTransitionTo($to)) {
            throw new LogicException("Transição de assinatura inválida: {$from->value} → {$to->value}.");
        }

        $now = CarbonImmutable::now();
        $graceDays = config('billing.grace_days');

        $subscription->status = $to;

        match ($to) {
            SubscriptionStatus::PastDue => $subscription->grace_ends_at = $now->addDays(is_int($graceDays) ? $graceDays : 7),
            SubscriptionStatus::Suspended => $subscription->suspended_at = $now,
            SubscriptionStatus::Active => [$subscription->grace_ends_at, $subscription->suspended_at] = [null, null],
            SubscriptionStatus::Canceled => [$subscription->canceled_at, $subscription->ends_at] = [$now, $subscription->ends_at ?? $now],
            SubscriptionStatus::Trialing => null,
        };

        $subscription->save();
        $this->entitlements->flush();

        $this->audit->record('subscription.status_changed', ['from' => $from->value, 'to' => $to->value, 'reason' => $reason], $subscription);
        event(new SubscriptionStatusChanged($subscription, $from, $to));

        return $subscription;
    }
}
