<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usuário autenticado precisa ser membro do tenant do host e a sessão
 * precisa ter sido aberta neste tenant (defesa extra além do cookie por host).
 */
final readonly class EnsureTenantMember
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Rotas centrais (sem tenant) não têm membership a verificar.
        if (! $user instanceof User || ! $this->context->check()) {
            return $next($request);
        }

        $sessionTenant = $request->hasSession() ? $request->session()->get('tenant_id') : null;
        $sessionMismatch = $sessionTenant !== null && $sessionTenant !== $this->context->id();

        if ($user->membership() === null || $sessionMismatch) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
            }

            abort(403, 'Você não tem acesso a esta organização.');
        }

        if ($request->hasSession() && $sessionTenant === null) {
            $request->session()->put('tenant_id', $this->context->id());
        }

        return $next($request);
    }
}
