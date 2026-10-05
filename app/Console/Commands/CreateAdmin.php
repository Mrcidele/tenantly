<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

final class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {name} {--password=}';

    protected $description = 'Cria um usuário do painel central';

    public function handle(): int
    {
        $password = $this->option('password');
        $password = is_string($password) && $password !== '' ? $password : $this->secret('Senha');

        Admin::query()->create([
            'email' => (string) $this->argument('email'),
            'name' => (string) $this->argument('name'),
            'password' => is_string($password) ? $password : '',
        ]);

        $this->info('Admin criado.');

        return self::SUCCESS;
    }
}
