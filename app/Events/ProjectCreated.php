<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Project;

final class ProjectCreated extends TenantBroadcastEvent
{
    public function __construct(public readonly Project $project)
    {
        parent::__construct();
    }

    /**
     * @return array{id: string, name: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->project->id, 'name' => $this->project->name];
    }
}
