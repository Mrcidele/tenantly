<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Audit\AuditLogger;
use App\Auth\TenantHandoff;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\UserTenants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class OrganizationController extends Controller
{
    public function index(Request $request, UserTenants $tenants): Response|JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $organizations = array_map(static fn (array $entry): array => [
            'id' => $entry['tenant']->id,
            'name' => $entry['tenant']->name,
            'slug' => $entry['tenant']->slug,
            'role' => $entry['role']->value,
        ], $tenants->for($user));

        if ($request->wantsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json(['organizations' => $organizations]);
        }

        return Inertia::render('Organizations/Index', ['organizations' => $organizations]);
    }

    public function switch(Request $request, Tenant $organization, UserTenants $tenants, TenantHandoff $handoff, AuditLogger $audit): HttpResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        abort_unless($tenants->belongsTo($user, $organization), 404);

        $audit->record('organization.switched', ['to' => $organization->id]);

        return Inertia::location($handoff->issue($user->id, $organization));
    }
}
