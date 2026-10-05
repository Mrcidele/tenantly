<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case PendingDeletion = 'pending_deletion';

    public function isAccessible(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Em provisionamento',
            self::Active => 'Ativo',
            self::Suspended => 'Suspenso',
            self::PendingDeletion => 'Exclusão agendada',
        };
    }
}
