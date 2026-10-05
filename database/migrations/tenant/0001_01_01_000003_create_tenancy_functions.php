<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sem tenant definido o resultado é NULL e nenhuma política casa: falha fechada.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION current_tenant_id() RETURNS uuid
            LANGUAGE sql STABLE PARALLEL SAFE
            AS $$ SELECT NULLIF(current_setting('app.tenant_id', true), '')::uuid $$
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION current_app_user_id() RETURNS uuid
            LANGUAGE sql STABLE PARALLEL SAFE
            AS $$ SELECT NULLIF(current_setting('app.user_id', true), '')::uuid $$
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS current_app_user_id()');
        DB::statement('DROP FUNCTION IF EXISTS current_tenant_id()');
    }
};
