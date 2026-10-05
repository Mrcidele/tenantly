<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\PersonalAccessTokenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Token da API escopado ao tenant em que foi criado.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tokenable_type
 * @property string $tokenable_id
 * @property string $name
 * @property string $token
 * @property list<string>|null $abilities
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $expires_at
 */
class PersonalAccessToken extends SanctumToken
{
    /** @use HasFactory<PersonalAccessTokenFactory> */
    use BelongsToTenant, HasFactory, HasUuids;
}
