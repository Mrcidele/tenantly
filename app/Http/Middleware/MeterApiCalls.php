<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Entitlements\Entitlements;
use App\Entitlements\UsageMeter;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exige o recurso de API no plano e conta chamadas por tenant/mês. */
final readonly class MeterApiCalls
{
    public function __construct(
        private Entitlements $entitlements,
        private UsageMeter $meter,
        private TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->can(Feature::ApiAccess), 402, 'API indisponível no seu plano.');

        $max = $this->entitlements->limit(Limit::ApiCallsPerMonth);
        $used = $this->meter->increment($this->context->get()->id, Limit::ApiCallsPerMonth);

        if ($max !== null && $used > $max) {
            abort(429, 'Cota mensal de chamadas de API esgotada.');
        }

        $response = $next($request);

        if ($max !== null) {
            $response->headers->set('X-Quota-Limit', (string) $max);
            $response->headers->set('X-Quota-Remaining', (string) max(0, $max - $used));
        }

        return $response;
    }
}
