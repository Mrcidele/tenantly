<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Tenancy\Concerns\UsesCentralConnection;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuários vivem no banco central, mas a tabela tem RLS: um usuário só é
 * visível para si mesmo e para os tenants dos quais é membro.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, TwoFactorAuthenticatable, UsesCentralConnection;

    /** @var array<string, Membership|null> membership memorizada por tenant */
    private array $resolvedMemberships = [];

    /**
     * Memberships do tenant ativo (o global scope filtra pelo tenant atual).
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** Membership no tenant ativo (null fora de contexto ou se não for membro). */
    public function membership(): ?Membership
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return null;
        }

        if (! array_key_exists($tenantId, $this->resolvedMemberships)) {
            $this->resolvedMemberships[$tenantId] = $this->memberships()->first();
        }

        return $this->resolvedMemberships[$tenantId];
    }

    public function forgetMembership(): void
    {
        $this->resolvedMemberships = [];
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->membership()?->can($permission) ?? false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
