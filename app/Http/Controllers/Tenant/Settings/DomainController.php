<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Settings;

use App\Audit\AuditLogger;
use App\Domains\DomainVerifier;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class DomainController extends Controller
{
    private const string HOSTNAME = '/^(?=.{4,253}$)(?!-)(?:[a-z0-9-]{1,63}(?<!-)\.)+[a-z]{2,63}$/';

    public function index(): Response
    {
        $this->authorize(Permission::ManageDomains->value);

        return Inertia::render('Settings/Domains', [
            'domains' => TenantDomain::query()->oldest()->get()->map(static fn (TenantDomain $d): array => [
                'id' => $d->id,
                'domain' => $d->domain,
                'status' => $d->status->value,
                'is_primary' => $d->is_primary,
                'verified_at' => $d->verified_at,
                'last_checked_at' => $d->last_checked_at,
                'failure_reason' => $d->failure_reason,
                'txt_name' => $d->verificationRecordName(),
                'txt_value' => $d->verification_token,
            ]),
            'cnameTarget' => config('tenancy.cname_target'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::ManageDomains->value);

        $domain = Str::lower(trim($request->string('domain')->value(), " \t\n\r\0\x0B."));

        if (preg_match(self::HOSTNAME, $domain) !== 1) {
            throw ValidationException::withMessages(['domain' => 'Informe um domínio válido, ex.: app.suaempresa.com.br']);
        }

        if ($domain === Tenant::centralDomain() || str_ends_with($domain, '.'.Tenant::centralDomain())) {
            throw ValidationException::withMessages(['domain' => 'Use um domínio próprio.']);
        }

        // Unicidade global sem revelar a qual tenant o domínio pertence.
        try {
            $created = TenantDomain::query()->create(['domain' => $domain]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['domain' => 'Este domínio não está disponível.']);
        }

        $audit->record('domain.added', ['domain' => $domain], $created);

        return back()->with('status', 'Domínio adicionado. Configure o DNS e clique em verificar.');
    }

    public function verify(TenantDomain $domain, DomainVerifier $verifier): RedirectResponse
    {
        $this->authorize(Permission::ManageDomains->value);

        return back()->with('status', $verifier->verify($domain) ? 'Domínio verificado.' : 'Ainda não foi possível verificar: '.$domain->failure_reason);
    }

    public function primary(TenantDomain $domain, DomainVerifier $verifier): RedirectResponse
    {
        $this->authorize(Permission::ManageDomains->value);
        abort_unless($domain->isVerified(), 422, 'Verifique o domínio antes de torná-lo principal.');

        $verifier->makePrimary($domain);

        return back();
    }

    public function destroy(TenantDomain $domain, DomainVerifier $verifier, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::ManageDomains->value);

        $domain->delete();
        $verifier->syncPrimary($domain);
        $audit->record('domain.removed', ['domain' => $domain->domain]);

        return back();
    }
}
