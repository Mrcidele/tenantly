<?php

declare(strict_types=1);

use App\Tenancy\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Snapshot persistido dos contadores de uso mantidos no Redis. */
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->string('metric', 32);
            $table->string('period', 16); // "2026-10" ou "total"
            $table->unsignedBigInteger('value');
            $table->timestamps();

            $table->unique(['tenant_id', 'metric', 'period']);
        });

        TenantSchema::enableRowLevelSecurity('usage_records');
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
