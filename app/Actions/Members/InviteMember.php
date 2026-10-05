<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Audit\AuditLogger;
use App\Entitlements\Entitlements;
use App\Enums\Limit;
use App\Enums\MembershipRole;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class InviteMember
{
    public const int EXPIRES_IN_DAYS = 7;

    public function __construct(
        private TenantContext $context,
        private UrlGenerator $url,
        private AuditLogger $audit,
        private Entitlements $entitlements,
    ) {}

    public function handle(User $inviter, string $email, MembershipRole $role): Invitation
    {
        $email = Str::lower(trim($email));
        $inviterRole = $inviter->membership()?->role;

        if ($inviterRole === null || ! in_array($role, $inviterRole->assignable(), true)) {
            throw ValidationException::withMessages(['role' => 'Você não pode convidar com este papel.']);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este e-mail já é membro da organização.']);
        }

        $this->entitlements->ensure(Limit::Users);

        // Um convite pendente por e-mail: reenviar substitui o anterior.
        Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

        $token = Str::random(48);
        $invitation = Invitation::query()->create([
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
        ]);

        $acceptUrl = $this->url->temporarySignedRoute(
            'invitations.accept',
            $invitation->expires_at,
            ['invitation' => $invitation->id, 'token' => $token],
        );

        Mail::to($email)->send(new InvitationMail($invitation, $this->context->get(), $acceptUrl));

        $this->audit->record('member.invited', ['email' => $email, 'role' => $role->value], $invitation);

        return $invitation;
    }
}
