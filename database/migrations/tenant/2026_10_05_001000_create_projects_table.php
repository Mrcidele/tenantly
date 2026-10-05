<?php

declare(strict_types=1);

use App\Tenancy\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->string('name');
            $table->text('description')->nullable();
            // Usuários vivem no banco central; sem FK para permitir banco dedicado.
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
        });

        TenantSchema::enableRowLevelSecurity('projects');

        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->uuid('project_id');
            $table->string('title');
            $table->string('status', 16)->default('todo');
            $table->uuid('assignee_id')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();

            // FK composta: uma tarefa nunca aponta para projeto de outro tenant.
            $table->foreign(['project_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('projects')
                ->cascadeOnDelete();
        });

        TenantSchema::enableRowLevelSecurity('tasks');
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
