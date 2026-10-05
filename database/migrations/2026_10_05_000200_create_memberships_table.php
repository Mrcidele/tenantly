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
        Schema::create('memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            TenantSchema::tenantColumn($table);
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        TenantSchema::enableRowLevelSecurity('memberships');

        // O usuário enxerga as próprias memberships em todos os tenants
        // (seletor de organização), mas não as dos outros.
        DB::statement('CREATE POLICY member_self ON memberships FOR SELECT USING (user_id = current_app_user_id())');

        // Usuários: visíveis para si mesmos e para os tenants dos quais são membros.
        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE users FORCE ROW LEVEL SECURITY');

        $visible = 'id = current_app_user_id() OR EXISTS ('
            .'SELECT 1 FROM memberships m WHERE m.user_id = users.id AND m.tenant_id = current_tenant_id())';

        DB::statement("CREATE POLICY user_visibility ON users FOR SELECT USING ({$visible})");
        DB::statement('CREATE POLICY user_signup ON users FOR INSERT WITH CHECK (true)');
        DB::statement("CREATE POLICY user_update ON users FOR UPDATE USING ({$visible}) WITH CHECK (true)");
        DB::statement('CREATE POLICY user_delete ON users FOR DELETE USING (id = current_app_user_id())');
    }

    public function down(): void
    {
        foreach (['user_visibility', 'user_signup', 'user_update', 'user_delete'] as $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON users");
        }

        DB::statement('ALTER TABLE users NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY');

        Schema::dropIfExists('memberships');
    }
};
