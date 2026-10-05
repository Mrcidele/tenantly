<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Permission;
use App\Events\ProjectCreated;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function index(): Response
    {
        $this->authorize(Permission::ViewProjects->value);

        return Inertia::render('Projects/Index', [
            'projects' => Project::query()->withCount('tasks')->latest()->get(),
        ]);
    }

    public function show(Project $project): Response
    {
        $this->authorize(Permission::ViewProjects->value);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'tasks' => $project->tasks()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize(Permission::ManageProjects->value);

        $user = $request->user();
        assert($user instanceof User);

        $project = Project::query()->create([...$this->attributes($request), 'created_by' => $user->id]);
        ProjectCreated::dispatch($project);

        return redirect()->route('projects.show', $project);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize(Permission::ManageProjects->value);

        $project->update($this->attributes($request));

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize(Permission::ManageProjects->value);

        $project->delete();

        return redirect()->route('projects.index');
    }

    /**
     * @return array{name: string, description: string|null}
     */
    private function attributes(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        return [
            'name' => $request->string('name')->value(),
            'description' => $request->filled('description') ? $request->string('description')->value() : null,
        ];
    }
}
