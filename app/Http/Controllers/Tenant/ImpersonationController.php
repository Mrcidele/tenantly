<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Auth\Impersonator;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ImpersonationController extends Controller
{
    public function destroy(Request $request, Impersonator $impersonator): Response
    {
        $impersonator->stop($request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();

        $admin = config('tenancy.admin_domain');

        return redirect()->away('http://'.(is_string($admin) ? $admin : Tenant::centralDomain()).'/');
    }
}
