<?php

declare(strict_types=1);

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (TenantContext $tenancy) => ['tenant' => $tenancy->get()->only(['id', 'name', 'slug'])])->name('tenant.home');
