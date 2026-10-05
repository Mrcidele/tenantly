<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SecurityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('Settings/Security', [
            'twoFactorEnabled' => $user->two_factor_secret !== null,
            'twoFactorConfirmed' => $user->two_factor_confirmed_at !== null,
        ]);
    }
}
