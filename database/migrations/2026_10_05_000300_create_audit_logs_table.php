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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table, nullable: true);
            $table->string('actor_type', 16)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->uuid('impersonator_id')->nullable();
            $table->string('action', 128);
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        TenantSchema::enableRowLevelSecurity('audit_logs', allowCentralInserts: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
