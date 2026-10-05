<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\PlatformMetrics;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(PlatformMetrics $metrics): Response
    {
        return Inertia::render('Admin/Dashboard', ['metrics' => $metrics->summary()]);
    }
}
