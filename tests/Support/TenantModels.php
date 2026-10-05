<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Descobre models automaticamente: um model novo entra nos testes de
 * isolamento sem que ninguém precise lembrar de registrá-lo.
 */
final class TenantModels
{
    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        $models = [];

        foreach ((new Finder)->files()->in(dirname(__DIR__, 2).'/app/Models')->name('*.php') as $file) {
            $class = 'App\\Models\\'.Str::of($file->getRelativePathname())->beforeLast('.php')->replace('/', '\\');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var class-string<Model> $class */
            $models[] = $class;
        }

        sort($models);

        return $models;
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function tenantScoped(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $class): bool => in_array(BelongsToTenant::class, class_uses_recursive($class), true),
        ));
    }
}
