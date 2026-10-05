<?php

declare(strict_types=1);

namespace App\Enums;

/** Limites numéricos por plano (-1 = ilimitado). */
enum Limit: string
{
    case Users = 'users';
    case Projects = 'projects';
    case StorageMb = 'storage_mb';
    case ApiCallsPerMonth = 'api_calls_per_month';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Usuários',
            self::Projects => 'Projetos',
            self::StorageMb => 'Armazenamento (MB)',
            self::ApiCallsPerMonth => 'Chamadas de API por mês',
        };
    }
}
