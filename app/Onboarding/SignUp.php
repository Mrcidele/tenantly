<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Audit\AuditLogger;
use App\Enums\MembershipRole;
use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SignUp
{
    public function __construct(
        private TenantContext $context,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{tenant: Tenant, user: User}
     */
    public function handle(string $organization, string $subdomain, string $name, string $email, string $password): array
    {
        $tenant = Tenant::query()->create([
            'name' => $organization,
            'slug' => $subdomain,
            'status' => TenantStatus::Provisioning,
        ]);

        try {
            $user = $this->context->run($tenant, function () use ($name, $email, $password): User {
                $user = User::query()->create(['name' => $name, 'email' => Str::lower($email), 'password' => $password]);
                Membership::query()->create(['user_id' => $user->id, 'role' => MembershipRole::Owner]);

                $this->audit->record('tenant.signed_up', ['slug' => $this->context->get()->slug]);

                // Dentro do contexto: o job leva o tenant_id no payload.
                ProvisionTenant::dispatch($this->context->get()->id);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            $tenant->delete();

            throw ValidationException::withMessages([
                'email' => 'Este e-mail já tem conta. Entre na sua organização e crie a nova a partir dela.',
            ]);
        }

        return ['tenant' => $tenant, 'user' => $user];
    }
}
