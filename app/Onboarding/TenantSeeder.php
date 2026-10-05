<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Enums\TaskStatus;
use App\Models\Project;

/** Dados iniciais de cada tenant. Idempotente. */
final class TenantSeeder
{
    public function run(): void
    {
        if (Project::query()->exists()) {
            return;
        }

        $project = Project::query()->create([
            'name' => 'Primeiros passos',
            'description' => 'Projeto de exemplo criado no cadastro.',
        ]);

        foreach (['Convidar a equipe' => TaskStatus::Todo, 'Configurar o domínio' => TaskStatus::Todo, 'Criar a conta' => TaskStatus::Done] as $title => $status) {
            $project->tasks()->create(['title' => $title, 'status' => $status]);
        }
    }
}
