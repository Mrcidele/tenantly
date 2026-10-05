<?php

declare(strict_types=1);

namespace App\Domains;

use App\Audit\AuditLogger;
use App\Enums\DomainStatus;
use App\Models\TenantDomain;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Verifica posse (TXT `_tenantly.<domínio>` com o token) e apontamento
 * (CNAME para o alvo da plataforma). Deve rodar no contexto do tenant dono.
 */
final readonly class DomainVerifier
{
    public function __construct(
        private DnsResolver $dns,
        private TenantContext $context,
        private AuditLogger $audit,
    ) {}

    public function verify(TenantDomain $domain): bool
    {
        $target = config('tenancy.cname_target');
        $ownership = in_array($domain->verification_token, array_map('trim', $this->dns->txt($domain->verificationRecordName())), true);
        $pointing = in_array(is_string($target) ? strtolower($target) : '', $this->dns->cname($domain->domain), true);

        $wasVerified = $domain->isVerified();
        $domain->last_checked_at = CarbonImmutable::now();

        if ($ownership && $pointing) {
            $domain->status = DomainStatus::Verified;
            $domain->verified_at ??= CarbonImmutable::now();
            $domain->failure_reason = null;
        } else {
            $domain->status = DomainStatus::Failed;
            $domain->verified_at = null;
            $domain->failure_reason = ! $ownership ? 'Registro TXT de verificação não encontrado.' : 'CNAME não aponta para '.(is_string($target) ? $target : '').'.';
        }

        $domain->save();
        $this->syncPrimary($domain);

        if ($wasVerified !== $domain->isVerified()) {
            $this->audit->record($domain->isVerified() ? 'domain.verified' : 'domain.verification_lost', ['domain' => $domain->domain], $domain);
        }

        return $domain->isVerified();
    }

    public function makePrimary(TenantDomain $domain): void
    {
        TenantDomain::query()->whereKeyNot($domain->id)->update(['is_primary' => false]);
        $domain->is_primary = true;
        $domain->save();

        $this->syncPrimary($domain);
    }

    /** Mantém tenants.primary_domain coerente: só um domínio primário E verificado. */
    public function syncPrimary(TenantDomain $domain): void
    {
        $tenant = $this->context->get();
        $primary = TenantDomain::query()->where('is_primary', true)->whereNotNull('verified_at')->value('domain');

        $tenant->forceFill(['primary_domain' => is_string($primary) ? $primary : null])->save();
    }
}
