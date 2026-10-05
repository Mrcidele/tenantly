<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\TenantFileController;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (TenantContext $tenancy) => ['tenant' => $tenancy->get()->only(['id', 'name', 'slug'])])->name('tenant.home');

Route::get('/files/{path}', [TenantFileController::class, 'show'])
    ->where('path', '.*')
    ->middleware('signed')
    ->name('tenant.files.show');
