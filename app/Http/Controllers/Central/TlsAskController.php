<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Tenancy\Resolution\OnDemandTlsAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\IpUtils;

final class TlsAskController extends Controller
{
    public function __invoke(Request $request, OnDemandTlsAuthorizer $authorizer): Response
    {
        // Endpoint interno (Caddy na rede privada): fora dela não responde, para não virar oráculo de enumeração.
        abort_unless(IpUtils::isPrivateIp((string) $request->ip()), 404);

        return response('', $authorizer->allows($request->string('domain')->value()) ? 200 : 404);
    }
}
