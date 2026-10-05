<?php

declare(strict_types=1);

namespace App\Tenancy\Contracts;

use App\Models\Tenant;

/**
 * Onde ficam os dados de domínio de um tenant. Identidade, assinaturas e
 * auditoria permanecem sempre no banco central.
 */
interface TenantDatabaseStrategy
{
    /** Nome da conexão usada pelos models que implementam StoresTenantData. */
    public function dataConnection(Tenant $tenant): string;

    /** Prepara o armazenamento do tenant (idempotente). */
    public function provision(Tenant $tenant): void;
}
