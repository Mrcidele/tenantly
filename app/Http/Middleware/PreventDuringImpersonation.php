<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Suporte impersonando não altera senha, 2FA, tokens nem cobrança. */
final class PreventDuringImpersonation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has('impersonation_id')) {
            abort(403, 'Ação indisponível durante impersonação.');
        }

        return $next($request);
    }
}
