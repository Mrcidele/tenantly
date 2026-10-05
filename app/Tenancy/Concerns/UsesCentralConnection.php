<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use App\Tenancy\Database\ConnectionRouter;
use Illuminate\Database\Eloquent\Model;

/**
 * Models do banco central sem tenant_id (tenants, domínios, planos, usuários).
 * Dentro de withoutTenancy() passam a usar a conexão com BYPASSRLS.
 *
 * @mixin Model
 */
trait UsesCentralConnection
{
    public function getConnectionName(): string
    {
        return app(ConnectionRouter::class)->central();
    }
}
