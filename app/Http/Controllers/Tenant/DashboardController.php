<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Task;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'projects' => Project::query()->count(),
                'open_tasks' => Task::query()->where('status', '!=', TaskStatus::Done)->count(),
                'members' => Membership::query()->count(),
            ],
            'recentProjects' => Project::query()->latest()->limit(5)->get(['id', 'name', 'created_at']),
        ]);
    }
}
