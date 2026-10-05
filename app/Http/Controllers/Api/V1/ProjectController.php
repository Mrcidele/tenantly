<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAbility($request, Permission::ViewProjects);

        return response()->json(['data' => Project::query()->latest()->paginate(50)->items()]);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->ensureAbility($request, Permission::ViewProjects);

        return response()->json(['data' => $project]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAbility($request, Permission::ManageProjects);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $project = Project::query()->create([
            'name' => $request->string('name')->value(),
            'description' => $request->filled('description') ? $request->string('description')->value() : null,
        ]);

        return response()->json(['data' => $project], 201);
    }

    /** O token precisa da habilidade E o papel atual do usuário precisa permitir. */
    private function ensureAbility(Request $request, Permission $permission): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->tokenCan($permission->value) && $user->hasPermission($permission), 403);
    }
}
