<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Models\Tenant;
use App\Tenancy\Database\TenantPurger;
use App\Tenancy\Filesystem\TenantFiles;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Agendado (diário): apaga exportações vencidas e purga tenants cuja
 * exclusão foi solicitada e o prazo de retenção acabou.
 */
final class PurgeExpiredData implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(TenantContext $context, TenantPurger $purger, TenantFiles $files): void
    {
        /** @var list<string> $tenantIds */
        $tenantIds = $context->withoutTenancy('Limpeza de exportações vencidas', fn (): array => DataExport::query()
            ->where('expires_at', '<', now())
            ->distinct()
            ->pluck('tenant_id')
            ->all());

        foreach (Tenant::query()->whereIn('id', $tenantIds)->get() as $tenant) {
            $context->run($tenant, function () use ($files): void {
                DataExport::query()->where('expires_at', '<', now())->each(function (DataExport $export) use ($files): void {
                    if ($export->path !== null) {
                        $files->disk()->delete($export->path);
                    }
                    $export->update(['status' => 'expired', 'path' => null]);
                });
            });
        }

        Tenant::query()
            ->where('status', TenantStatus::PendingDeletion)
            ->where('purge_after', '<=', now())
            ->each(function (Tenant $tenant) use ($purger, $context): void {
                $base = config('tenancy.filesystem.base_disk');
                Storage::disk(is_string($base) ? $base : 'local')->deleteDirectory('tenants/'.$tenant->id);

                $removedUsers = $purger->purge($tenant);

                // Registro central (sem tenant) de que a purga ocorreu.
                $context->withoutTenancy('Registro de purga LGPD', fn () => AuditLog::query()->create([
                    'action' => 'tenant.purged',
                    'actor_type' => 'system',
                    'properties' => ['tenant_id' => $tenant->id, 'removed_users' => $removedUsers],
                ]));
            });
    }
}
