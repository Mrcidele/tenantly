<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Impersonation;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorize(Permission::ViewAuditLog->value);

        return Inertia::render('Audit/Index', [
            'logs' => AuditLog::query()->latest('created_at')->limit(200)->get(),
            'impersonations' => Impersonation::query()->latest('started_at')->limit(50)->get(),
        ]);
    }
}
