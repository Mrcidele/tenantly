<?php

declare(strict_types=1);

namespace App\Tenancy\Database;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Exporta todos os dados do tenant ativo (LGPD, direito de portabilidade).
 * Lê com a conexão da aplicação: o RLS garante que só linhas do tenant saem,
 * mesmo que a lista de tabelas cresça.
 */
final readonly class TenantDataExporter
{
    public function __construct(private TenantContext $context) {}

    /** @return string caminho local do .zip gerado */
    public function export(): string
    {
        $tenant = $this->context->get();
        $directory = storage_path('app/private/exports-tmp');

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório temporário.');
        }

        $path = $directory.'/'.$tenant->id.'-'.Str::random(16).'.zip';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o pacote de exportação.');
        }

        $zip->addFromString('organization.json', (string) json_encode($tenant->only(['id', 'name', 'slug', 'created_at', 'branding', 'settings']), JSON_PRETTY_PRINT));

        // Usuários visíveis sob o RLS = membros do tenant.
        $zip->addFromString('users.json', (string) json_encode(DB::select('select id, name, email, created_at from users order by created_at'), JSON_PRETTY_PRINT));

        $hidden = ['token', 'token_hash'];

        foreach (TenantTables::ordered() as $table) {
            $rows = array_map(static fn (object $row): array => array_diff_key((array) $row, array_flip($hidden)), DB::table($table)->get()->all());
            $zip->addFromString($table.'.json', (string) json_encode($rows, JSON_PRETTY_PRINT));
        }

        $zip->close();

        return $path;
    }
}
