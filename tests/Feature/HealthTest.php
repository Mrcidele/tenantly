<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('responde o health check', function (): void {
    $this->get('/up')->assertOk();
});

it('roda a aplicação com um papel de banco sem BYPASSRLS', function (): void {
    $role = DB::selectOne('select rolname, rolbypassrls, rolsuper from pg_roles where rolname = current_user');

    expect($role->rolname)->toBe('tenantly_app')
        ->and($role->rolbypassrls)->toBeFalse()
        ->and($role->rolsuper)->toBeFalse();
});
