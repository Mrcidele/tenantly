<?php

declare(strict_types=1);

namespace App\Auth;

use App\Audit\AuditLogger;
use App\Models\Admin;
use App\Models\Impersonation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Impersonação para suporte. Sempre exige motivo, grava um registro em
 * `impersonations` (visível ao próprio tenant) e entradas na auditoria; se
 * a auditoria falhar, a impersonação não acontece.
 */
final readonly class Impersonator
{
    public function __construct(
        private TenantContext $context,
        private TenantHandoff $handoff,
        private AuditLogger $audit,
    ) {}

    /** @return string URL de handoff no host do tenant */
    public function start(Admin $admin, Tenant $tenant, string $userId, string $reason, ?Request $request = null): string
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Descreva o motivo (mínimo 10 caracteres).']);
        }

        return $this->context->run($tenant, function () use ($admin, $tenant, $userId, $reason, $request): string {
            $user = User::query()->find($userId);

            if ($user === null || $user->membership() === null) {
                throw ValidationException::withMessages(['user' => 'Usuário não pertence a esta organização.']);
            }

            $impersonation = Impersonation::query()->create([
                'admin_id' => $admin->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'ip_address' => $request?->ip(),
                'started_at' => now(),
            ]);

            $this->audit->record('impersonation.started', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'user_id' => $user->id,
                'reason' => $reason,
            ], $impersonation);

            return $this->handoff->issue($user->id, $tenant, [
                'impersonation_id' => $impersonation->id,
                'impersonator_id' => $admin->id,
            ]);
        });
    }

    public function stop(Request $request): void
    {
        $id = $request->session()->get('impersonation_id');

        if (is_string($id)) {
            $impersonation = Impersonation::query()->find($id);
            $impersonation?->update(['ended_at' => now()]);
            $this->audit->record('impersonation.ended', [], $impersonation);
        }

        $request->session()->forget(['impersonation_id', 'impersonator_id']);
    }
}
