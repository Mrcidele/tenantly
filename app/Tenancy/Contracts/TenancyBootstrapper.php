<?php

declare(strict_types=1);

namespace App\Tenancy\Contracts;

use App\Models\Tenant;

/**
 * Ajusta um serviço global (cache, filesystem, URL, logs...) para o tenant
 * ativo. `revert()` deve ser idempotente: é chamado no início de toda
 * request/job, mesmo sem tenant ativo, para limpar estado de workers longos.
 */
interface TenancyBootstrapper
{
    public function bootstrap(Tenant $tenant): void;

    public function revert(): void;
}
