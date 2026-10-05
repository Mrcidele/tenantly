<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Rules\ValidSubdomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SubdomainAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $subdomain = Str::lower(trim((string) $request->string('subdomain')));
        $validator = Validator::make(['subdomain' => $subdomain], ['subdomain' => ['required', new ValidSubdomain]]);

        return response()->json([
            'available' => $validator->passes(),
            'message' => $validator->errors()->first('subdomain') ?: null,
        ]);
    }
}
