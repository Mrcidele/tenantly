<?php

declare(strict_types=1);

use App\Tenancy\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('status', 32)->default('provisioning')->index();
            $table->string('database_strategy', 16)->default('shared');
            $table->jsonb('database_config')->nullable();
            $table->jsonb('branding')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('limit_overrides')->nullable();
            $table->string('primary_domain')->nullable();
            $table->string('queue')->nullable();
            $table->jsonb('provisioning')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 16)->default('pending');
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });

        TenantSchema::enableRowLevelSecurity('tenant_domains');

        // Domínios verificados são públicos (DNS) e precisam ser lidos para
        // resolver o tenant antes de existir contexto.
        DB::statement('CREATE POLICY public_lookup ON tenant_domains FOR SELECT USING (verified_at IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
