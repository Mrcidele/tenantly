<?php

declare(strict_types=1);

namespace App\Audit\Concerns;

use App\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Audita criação, alteração (com diff) e exclusão do model, no tenant ativo.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(static fn (Model $model) => self::audit($model, 'created', ['attributes' => self::safe($model, $model->getAttributes())]));

        static::updated(static function (Model $model): void {
            $changes = array_diff_key($model->getChanges(), array_flip(['updated_at']));

            if ($changes !== []) {
                self::audit($model, 'updated', [
                    'before' => self::safe($model, array_intersect_key($model->getOriginal(), $changes)),
                    'after' => self::safe($model, $changes),
                ]);
            }
        });

        static::deleted(static fn (Model $model) => self::audit($model, 'deleted', []));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function audit(Model $model, string $event, array $properties): void
    {
        app(AuditLogger::class)->record(str($model::class)->classBasename()->snake().'.'.$event, $properties, $model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function safe(Model $model, array $attributes): array
    {
        return array_diff_key($attributes, array_flip([...$model->getHidden(), 'password', 'remember_token', 'token_hash', 'token']));
    }
}
