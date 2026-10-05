<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

arch('o código da aplicação usa strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('queries cruas (DB::) só existem na camada de infraestrutura de tenancy')
    ->expect('App')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'DB'])
    ->ignoring(['App\Tenancy\Database']);

arch('o gerenciador de banco não é injetado fora da infraestrutura')
    ->expect('App')
    ->not->toUse(['Illuminate\Database\DatabaseManager', 'Illuminate\Database\ConnectionResolverInterface'])
    ->ignoring(['App\Tenancy\Database', 'App\Providers\TenancyServiceProvider', 'App\Providers\AppServiceProvider']);

arch('enums ficam em App\Enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('sem funções de debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'print_r'])
    ->not->toBeUsed();

arch('env() só é lido nos arquivos de configuração')
    ->expect('App')
    ->not->toUse('env');

arch()->preset()->security()->ignoring('assert');

it('só remove o TenantScope dentro da camada de tenancy', function (): void {
    $offenders = [];

    foreach ((new Finder)->files()->in(dirname(__DIR__, 2).'/app')->name('*.php') as $file) {
        $path = str_replace('\\', '/', $file->getRelativePathname());

        if (str_starts_with($path, 'Tenancy/')) {
            continue;
        }

        if (preg_match('/withoutGlobalScopes\s*\(|withoutGlobalScope\s*\(\s*TenantScope/', $file->getContents()) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([], 'Use TenantContext::withoutTenancy() (auditado) em vez de remover o scope: '.implode(', ', $offenders));
});
