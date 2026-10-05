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
        // Equipe interna (painel central, guard "admin"). Sem relação com tenants.
        Schema::create('admins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Visível ao próprio tenant (transparência sobre acessos do suporte).
        Schema::create('impersonations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->foreignUuid('admin_id')->constrained('admins');
            $table->uuid('user_id');
            $table->text('reason');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        TenantSchema::enableRowLevelSecurity('impersonations');
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
        Schema::dropIfExists('admins');
    }
};
