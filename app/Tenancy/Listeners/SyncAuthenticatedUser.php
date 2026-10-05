<?php

declare(strict_types=1);

namespace App\Tenancy\Listeners;

use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Laravel\Sanctum\Events\TokenAuthenticated;

/** Expõe o usuário autenticado ao Postgres (app.user_id) via TenantContext. */
final readonly class SyncAuthenticatedUser
{
    public function __construct(private Container $container) {}

    public function handle(Authenticated|Login|Logout|TokenAuthenticated $event): void
    {
        $context = $this->container->make(TenantContext::class);

        if ($event instanceof Logout) {
            $context->setUser(null);

            return;
        }

        $user = $event instanceof TokenAuthenticated ? $event->token->tokenable : $event->user;
        $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : null;

        $context->setUser(is_string($id) ? $id : null);
    }
}
