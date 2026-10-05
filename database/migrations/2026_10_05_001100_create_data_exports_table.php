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
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->uuid('requested_by');
            $table->string('status', 16)->default('pending');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        TenantSchema::enableRowLevelSecurity('data_exports');
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
