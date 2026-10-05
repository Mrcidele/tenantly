<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::guard('admin')->attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();

        return redirect()->route('admin.login');
    }
}
