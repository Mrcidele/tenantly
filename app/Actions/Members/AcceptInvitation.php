<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Audit\AuditLogger;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final readonly class AcceptInvitation
{
    public function __construct(
        private TenantContext $context,
        private Hasher $hasher,
        private AuditLogger $audit,
    ) {}

    /**
     * Se o e-mail já tem conta (em outra organização), exige a senha dela;
     * senão cria a conta com nome e senha informados.
     */
    public function handle(Invitation $invitation, string $token, ?string $name, string $password): User
    {
        if (! $invitation->isPending() || ! $invitation->matchesToken($token)) {
            throw ValidationException::withMessages(['token' => 'Convite inválido ou expirado.']);
        }

        // A conta pode existir em outro tenant, invisível pelo RLS: busca auditada.
        $existing = $this->context->withoutTenancy(
            'Aceite de convite: localizar conta existente pelo e-mail convidado',
            fn (): ?User => User::query()->where('email', $invitation->email)->first(),
        );

        if ($existing !== null) {
            if (! $this->hasher->check($password, $existing->password)) {
                throw ValidationException::withMessages(['password' => 'Senha incorreta para a conta existente.']);
            }

            $userId = $existing->id;
        } else {
            if ($name === null || trim($name) === '') {
                throw ValidationException::withMessages(['name' => 'Informe seu nome.']);
            }

            $userId = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ])->id;
        }

        Membership::query()->create(['user_id' => $userId, 'role' => $invitation->role]);
        $invitation->forceFill(['accepted_at' => now()])->save();

        // Agora o usuário é membro e fica visível sob o RLS deste tenant.
        $user = User::query()->findOrFail($userId);
        Auth::guard('web')->login($user);

        $this->audit->record('member.joined', ['email' => $invitation->email, 'role' => $invitation->role->value], $invitation);

        return $user;
    }
}
