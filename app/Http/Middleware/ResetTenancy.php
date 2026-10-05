<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primeiro middleware global: garante que nenhuma request herde estado de
 * tenant de uma request anterior no mesmo worker (Octane).
 */
final readonly class ResetTenancy
{
    public function __construct(
        private TenantContext $context,
        private AuthFactory $auth,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->reset();

        // Usuário já resolvido antes da request (ex.: actingAs nos testes).
        $guard = $this->auth->guard();

        if ($guard->hasUser()) {
            $id = $guard->id();
            $this->context->setUser(is_string($id) ? $id : null);
        }

        return $next($request);
    }
}
