<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Login acontece no host do tenant. Sob o RLS, só membros deste tenant
        // são encontrados pelo e-mail: conta de outra organização = "não existe".
        Fortify::authenticateUsing(static function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower((string) $request->string('email')))->first();

            if ($user === null || ! Hash::check((string) $request->string('password'), $user->password)) {
                return null;
            }

            return $user->membership() !== null ? $user : null;
        });

        Fortify::loginView(static fn () => Inertia::render('Auth/Login'));
        Fortify::twoFactorChallengeView(static fn () => Inertia::render('Auth/TwoFactorChallenge'));
        Fortify::requestPasswordResetLinkView(static fn () => Inertia::render('Auth/ForgotPassword'));
        Fortify::resetPasswordView(static fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->value(),
        ]));
        Fortify::confirmPasswordView(static fn () => Inertia::render('Auth/ConfirmPassword'));

        // Rate limit por tenant + e-mail + IP.
        RateLimiter::for('login', static function (Request $request): Limit {
            $tenant = app(TenantContext::class)->id() ?? 'central';
            $key = $tenant.'|'.Str::lower((string) $request->string('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('two-factor', static function (Request $request): Limit {
            $id = $request->session()->get('login.id');

            return Limit::perMinute(5)->by(is_string($id) ? $id : (string) $request->ip());
        });
    }
}
