<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Entitlements\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assinatura suspensa ou cancelada: modo somente leitura. Leituras seguem
 * liberadas; escritas recebem 423, exceto as rotas necessárias para pagar,
 * trocar de plano ou sair.
 */
final readonly class EnsureSubscriptionWritable
{
    /** @var list<string> */
    private const array ALWAYS_ALLOWED = [
        'billing.*', 'logout', 'impersonation.destroy', 'two-factor.*', 'organizations.switch',
    ];

    public function __construct(private Entitlements $entitlements) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs(...self::ALWAYS_ALLOWED) || $this->entitlements->writable()) {
            return $next($request);
        }

        abort(423, 'Assinatura suspensa: a organização está em modo somente leitura até a regularização do pagamento.');
    }
}
