<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Entitlements\Entitlements;
use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso: ->middleware('feature:custom_domains') */
final readonly class RequireFeature
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->entitlements->can(Feature::from($feature)), 402, 'Recurso indisponível no seu plano.');

        return $next($request);
    }
}
