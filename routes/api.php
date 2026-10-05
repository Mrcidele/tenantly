<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\EnsureSubscriptionWritable;
use App\Http\Middleware\MeterApiCalls;
use Illuminate\Support\Facades\Route;

// Rotas carregadas com ['api', 'tenant:header,subdomain,domain'].

Route::prefix('v1')->name('v1.')->middleware(['auth:sanctum', MeterApiCalls::class, EnsureSubscriptionWritable::class])->group(function (): void {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
});
