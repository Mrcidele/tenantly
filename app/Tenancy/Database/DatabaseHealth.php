<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use Illuminate\Support\Facades\DB;

final class DatabaseHealth
{
    public function ping(): void
    {
        DB::connection()->select('select 1');
    }
}
