<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillingOverviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Middleware\EnsureCentralContext;
use Illuminate\Support\Facades\Route;

// Domínio do painel central, guard "admin", sem contexto de tenant.

Route::middleware(['guest:admin', EnsureCentralContext::class])->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth:admin', EnsureCentralContext::class])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('tenants.reactivate');
    Route::post('/tenants/{tenant}/limits', [TenantController::class, 'limits'])->name('tenants.limits');
    Route::post('/tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])->name('tenants.impersonate');
    Route::get('/plans', [BillingOverviewController::class, 'plans'])->name('plans');
    Route::get('/invoices', [BillingOverviewController::class, 'invoices'])->name('invoices');
});
