<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/** O painel central nunca roda com tenant ativo nem com sessão de usuário de tenant. */
final readonly class EnsureCentralContext
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->check()) {
            throw new LogicException('Painel central executando com tenant ativo.');
        }

        return $next($request);
    }
}
