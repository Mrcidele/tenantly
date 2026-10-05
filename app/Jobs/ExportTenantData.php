<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Audit\AuditLogger;
use App\Models\DataExport;
use App\Models\User;
use App\Notifications\DataExportReady;
use App\Tenancy\Database\TenantDataExporter;
use App\Tenancy\Filesystem\TenantFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;

/** Gera o pacote de exportação (roda no contexto do tenant, vindo do payload). */
final class ExportTenantData implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public readonly string $exportId) {}

    public function handle(TenantDataExporter $exporter, TenantFiles $files, AuditLogger $audit): void
    {
        $export = DataExport::query()->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);

        $local = $exporter->export();
        $path = $files->disk()->putFileAs('exports', new File($local), $export->id.'.zip');
        $size = (int) filesize($local);
        @unlink($local);

        $export->update([
            'status' => 'ready',
            'path' => is_string($path) ? $path : null,
            'size_bytes' => $size,
            'completed_at' => now(),
            'expires_at' => now()->addDays(DataExport::RETENTION_DAYS),
        ]);

        $audit->record('data.export_ready', ['size_bytes' => $size], $export);

        User::query()->find($export->requested_by)?->notify(new DataExportReady($export));
    }

    public function failed(): void
    {
        DataExport::query()->whereKey($this->exportId)->update(['status' => 'failed']);
    }
}
