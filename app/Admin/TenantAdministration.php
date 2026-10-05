<?php

declare(strict_types=1);

namespace App\Admin;

use App\Audit\AuditLogger;
use App\Entitlements\Entitlements;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\TenantStatus;
use App\Models\Admin;
use App\Models\Membership;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/** Ações do suporte sobre um tenant. Cada ação roda no contexto do tenant e é auditada lá. */
final readonly class TenantAdministration
{
    public function __construct(
        private TenantContext $context,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{subscription: Subscription|null, members: list<array{user_id: string, name: string, email: string, role: string}>, usage: array<string, array{used: int, limit: int|null}>}
     */
    public function overview(Tenant $tenant, Admin $admin): array
    {
        return $this->context->run($tenant, function () use ($admin): array {
            $this->audit->record('admin.tenant_viewed', ['admin_id' => $admin->id]);
            $entitlements = app(Entitlements::class);

            $usage = [];
            foreach (Limit::cases() as $limit) {
                $usage[$limit->value] = ['used' => $entitlements->usage($limit), 'limit' => $entitlements->limit($limit)];
            }

            return [
                'subscription' => Subscription::query()->with('plan')->first(),
                'members' => array_values(Membership::query()->with('user')->get()->map(static fn (Membership $m): array => [
                    'user_id' => $m->user_id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'role' => $m->role->value,
                ])->all()),
                'usage' => $usage,
            ];
        });
    }

    public function suspend(Tenant $tenant, Admin $admin, string $reason): void
    {
        $this->requireReason($reason);

        $tenant->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => now()])->save();
        $this->context->run($tenant, fn () => $this->audit->record('admin.tenant_suspended', ['admin_id' => $admin->id, 'reason' => $reason]));
    }

    public function reactivate(Tenant $tenant, Admin $admin, string $reason): void
    {
        $this->requireReason($reason);

        $tenant->forceFill(['status' => TenantStatus::Active, 'suspended_at' => null])->save();
        $this->context->run($tenant, fn () => $this->audit->record('admin.tenant_reactivated', ['admin_id' => $admin->id, 'reason' => $reason]));
    }

    /**
     * @param  array<string, int|bool|null>  $overrides  chaves de Limit/Feature; null remove o ajuste
     */
    public function adjustLimits(Tenant $tenant, Admin $admin, array $overrides, string $reason): void
    {
        $this->requireReason($reason);

        $current = $tenant->limit_overrides ?? [];

        foreach ($overrides as $key => $value) {
            $limit = Limit::tryFrom($key);
            $feature = Feature::tryFrom($key);

            if ($limit === null && $feature === null) {
                throw ValidationException::withMessages(['overrides' => "Chave desconhecida: {$key}"]);
            }

            if ($value === null) {
                unset($current[$key]);
            } elseif ($limit !== null && is_int($value) && $value >= -1) {
                $current[$key] = $value;
            } elseif ($feature !== null && is_bool($value)) {
                $current[$key] = $value;
            } else {
                throw ValidationException::withMessages(['overrides' => "Valor inválido para {$key}"]);
            }
        }

        $before = $tenant->limit_overrides;
        $tenant->forceFill(['limit_overrides' => $current === [] ? null : $current])->save();

        $this->context->run($tenant, fn () => $this->audit->record('admin.limits_adjusted', [
            'admin_id' => $admin->id, 'reason' => $reason, 'before' => $before, 'after' => $current,
        ]));
    }

    private function requireReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Descreva o motivo (mínimo 10 caracteres).']);
        }
    }
}
