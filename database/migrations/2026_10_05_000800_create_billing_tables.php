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
        Schema::create('plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('interval', 8)->default('month');
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 64);
            // Booleano ("true"/"false") para features ou inteiro para limites (-1 = ilimitado).
            $table->string('value', 32);
            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
        });

        // Mapeia o cliente do gateway para o tenant sem precisar ler dados de tenant.
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('billing_gateway', 16)->nullable();
            $table->string('billing_customer_id')->nullable()->unique();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->foreignUuid('plan_id')->constrained();
            $table->string('status', 16);
            $table->string('gateway', 16);
            $table->string('gateway_subscription_id')->nullable()->unique();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->index(['status', 'grace_ends_at']);
        });

        TenantSchema::enableRowLevelSecurity('subscriptions');

        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->foreignUuid('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway_invoice_id')->nullable()->unique();
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 16);
            $table->string('method', 16)->nullable();
            $table->string('description');
            $table->text('payment_url')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        TenantSchema::enableRowLevelSecurity('invoices');

        Schema::create('billing_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('gateway', 16);
            $table->string('event_id');
            $table->string('type', 64);
            $table->jsonb('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['billing_gateway', 'billing_customer_id']);
        });
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
