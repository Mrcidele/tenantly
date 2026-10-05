<?php

declare(strict_types=1);

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Broadcast;

/*
 * A rota de autorização roda com o middleware "tenant": só é possível
 * assinar canais do tenant do host atual, e apenas sendo membro dele.
 */

Broadcast::channel('tenant.{tenantId}', function (User $user, string $tenantId): bool {
    return $tenantId === app(TenantContext::class)->id() && $user->membership() !== null;
});

Broadcast::channel('tenant.{tenantId}.user.{userId}', function (User $user, string $tenantId, string $userId): bool {
    return $tenantId === app(TenantContext::class)->id() && $userId === $user->id && $user->membership() !== null;
});
