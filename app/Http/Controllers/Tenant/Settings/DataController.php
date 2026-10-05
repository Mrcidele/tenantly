<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Settings;

use App\Audit\AuditLogger;
use App\Enums\Permission;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ExportTenantData;
use App\Models\DataExport;
use App\Models\User;
use App\Tenancy\Filesystem\TenantFiles;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** LGPD: exportação dos dados e exclusão da organização. */
final class DataController extends Controller
{
    public const int DELETION_RETENTION_DAYS = 30;

    public function index(): Response
    {
        $this->authorize(Permission::ExportData->value);

        return Inertia::render('Settings/Data', [
            'exports' => DataExport::query()->latest()->limit(10)->get(['id', 'status', 'size_bytes', 'completed_at', 'expires_at', 'created_at']),
            'retentionDays' => self::DELETION_RETENTION_DAYS,
        ]);
    }

    public function export(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::ExportData->value);
        $user = $request->user();
        assert($user instanceof User);

        $export = DataExport::query()->create(['requested_by' => $user->id]);
        ExportTenantData::dispatch($export->id);
        $audit->record('data.export_requested', [], $export);

        return back()->with('status', 'Exportação solicitada. Você receberá um e-mail quando estiver pronta.');
    }

    public function download(DataExport $export, TenantFiles $files): RedirectResponse
    {
        $this->authorize(Permission::ExportData->value);
        abort_unless($export->status === 'ready' && $export->path !== null, 404);

        return redirect()->away($files->temporaryUrl($export->path, 10));
    }

    public function destroyOrganization(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::DeleteOrganization->value);
        $user = $request->user();
        assert($user instanceof User);

        $tenant = $context->get();

        if (! Hash::check($request->string('password')->value(), $user->password) || $request->string('confirm')->value() !== $tenant->slug) {
            throw ValidationException::withMessages(['password' => 'Confirme com sua senha e o endereço da organização.']);
        }

        $tenant->forceFill([
            'status' => TenantStatus::PendingDeletion,
            'deletion_requested_at' => now(),
            'purge_after' => now()->addDays(self::DELETION_RETENTION_DAYS),
        ])->save();

        $audit->record('organization.deletion_requested', ['purge_after' => $tenant->purge_after?->toIso8601String()]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();

        return redirect()->away('http://'.\App\Models\Tenant::centralDomain().'/');
    }
}
