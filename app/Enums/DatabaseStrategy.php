<?php

declare(strict_types=1);

namespace App\Enums;

enum DatabaseStrategy: string
{
    // Banco compartilhado, isolamento por tenant_id + RLS.
    case Shared = 'shared';
    // Dados de domínio em banco dedicado (clientes enterprise).
    case Dedicated = 'dedicated';
}
