<?php

declare(strict_types=1);

use App\Http\Controllers\Central\SignupController;
use App\Http\Controllers\Central\SubdomainAvailabilityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing'))->name('home');

Route::get('/signup', [SignupController::class, 'create'])->name('signup');
Route::post('/signup', [SignupController::class, 'store'])->middleware('throttle:signup')->name('signup.store');
Route::get('/signup/check-subdomain', SubdomainAvailabilityController::class)->middleware('throttle:subdomain-check')->name('signup.check');
Route::get('/signup/{tenant}/status', [SignupController::class, 'status'])->name('signup.status');
Route::post('/signup/{tenant}/continue', [SignupController::class, 'continue'])->name('signup.continue');
