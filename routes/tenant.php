<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\ApiTokenController;
use App\Http\Controllers\Tenant\AuditLogController;
use App\Http\Controllers\Tenant\BillingController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\HandoffController;
use App\Http\Controllers\Tenant\ImpersonationController;
use App\Http\Controllers\Tenant\InvitationController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\OrganizationController;
use App\Http\Controllers\Tenant\ProjectController;
use App\Http\Controllers\Tenant\Settings\BrandingController;
use App\Http\Controllers\Tenant\Settings\DataController;
use App\Http\Controllers\Tenant\Settings\DomainController;
use App\Http\Controllers\Tenant\Settings\SecurityController;
use App\Http\Controllers\Tenant\TenantFileController;
use App\Http\Middleware\EnsureSubscriptionWritable;
use App\Http\Middleware\PreventDuringImpersonation;
use App\Tenancy\Branding;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

// Rotas carregadas com ['web', 'tenant']: o tenant já está identificado.

Route::get('/.well-known/tenant', fn (TenantContext $tenancy, Branding $branding) => [
    'tenant' => [...$tenancy->get()->only(['id', 'name', 'slug']), 'branding' => $branding->for($tenancy->get())],
])->name('tenant.info');

Route::get('/auth/handoff', HandoffController::class)->name('auth.handoff');

Route::middleware('signed')->group(function (): void {
    Route::get('/invitations/{invitation}/accept', [InvitationController::class, 'show'])->name('invitations.accept');
    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept.store');
    Route::get('/files/{path}', [TenantFileController::class, 'show'])->where('path', '.*')->name('tenant.files.show');
});

Route::middleware(['auth', EnsureSubscriptionWritable::class])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('projects', ProjectController::class)->except(['create', 'edit']);

    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::patch('/members/{member}', [MemberController::class, 'update'])->name('members.update');
    Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    Route::get('/settings/branding', [BrandingController::class, 'edit'])->name('settings.branding');
    Route::put('/settings/branding', [BrandingController::class, 'update'])->name('settings.branding.update');

    Route::middleware('feature:custom_domains')->group(function (): void {
        Route::get('/settings/domains', [DomainController::class, 'index'])->name('domains.index');
        Route::post('/settings/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::post('/settings/domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::post('/settings/domains/{domain}/primary', [DomainController::class, 'primary'])->name('domains.primary');
        Route::delete('/settings/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
    });

    Route::middleware('feature:data_export')->group(function (): void {
        Route::get('/settings/data', [DataController::class, 'index'])->name('data.index');
        Route::post('/settings/data/exports', [DataController::class, 'export'])->middleware('throttle:3,60')->name('data.export');
        Route::get('/settings/data/exports/{export}', [DataController::class, 'download'])->name('data.download');
    });
    Route::post('/settings/organization/delete', [DataController::class, 'destroyOrganization'])
        ->middleware(PreventDuringImpersonation::class)
        ->name('organization.destroy');

    Route::get('/audit', AuditLogController::class)->middleware('feature:audit_log')->name('audit.index');
    Route::delete('/impersonation', [ImpersonationController::class, 'destroy'])->name('impersonation.destroy');

    Route::middleware(PreventDuringImpersonation::class)->group(function (): void {
        Route::get('/settings/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('/settings/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('/settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

        Route::get('/settings/security', SecurityController::class)->name('settings.security');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/preview', [BillingController::class, 'preview'])->name('billing.preview');
        Route::post('/billing/plan', [BillingController::class, 'changePlan'])->name('billing.plan');
        Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    });
});
