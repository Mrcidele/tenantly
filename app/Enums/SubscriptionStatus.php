<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida da assinatura (máquina de estados explícita):
 *
 *   trialing ─┬─> active ──> past_due ──> suspended ──> canceled
 *             │     ^           │  ^          │
 *             │     └───────────┘  └──────────┘ (pagamento)
 *             └──> past_due / canceled
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Canceled = 'canceled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::PastDue, self::Canceled],
            self::Active => [self::PastDue, self::Canceled],
            self::PastDue => [self::Active, self::Suspended, self::Canceled],
            self::Suspended => [self::Active, self::Canceled],
            self::Canceled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Suspensa ou cancelada: modo somente leitura. */
    public function allowsWrites(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Em avaliação',
            self::Active => 'Ativa',
            self::PastDue => 'Pagamento pendente',
            self::Suspended => 'Suspensa',
            self::Canceled => 'Cancelada',
        };
    }
}
