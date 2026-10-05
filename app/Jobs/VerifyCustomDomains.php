<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\DomainVerifier;
use App\Enums\DomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Agendado: verifica domínios pendentes e revalida os verificados (DNS pode
 * mudar a qualquer momento). Cada domínio é verificado no contexto do seu tenant.
 */
final class VerifyCustomDomains implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly bool $includeVerified = false) {}

    public function handle(TenantContext $context, DomainVerifier $verifier): void
    {
        /** @var list<array{id: string, tenant_id: string}> $domains */
        $domains = $context->withoutTenancy('Verificação agendada de domínios customizados', fn (): array => TenantDomain::query()
            ->when(! $this->includeVerified, fn ($q) => $q->where('status', '!=', DomainStatus::Verified))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(5)))
            ->get(['id', 'tenant_id'])
            ->map(static fn (TenantDomain $d): array => ['id' => $d->id, 'tenant_id' => $d->tenant_id])
            ->all());

        foreach ($domains as $row) {
            $tenant = Tenant::query()->find($row['tenant_id']);

            if ($tenant === null) {
                continue;
            }

            $context->run($tenant, function () use ($verifier, $row): void {
                $domain = TenantDomain::query()->find($row['id']);

                if ($domain !== null) {
                    $verifier->verify($domain);
                }
            });
        }
    }
}
