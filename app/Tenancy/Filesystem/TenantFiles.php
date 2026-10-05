<?php

declare(strict_types=1);

namespace App\Tenancy\Filesystem;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Routing\UrlGenerator;

/** Acesso aos arquivos do tenant ativo e geração de URLs assinadas. */
final readonly class TenantFiles
{
    public function __construct(
        private FilesystemManager $filesystem,
        private UrlGenerator $url,
    ) {}

    public function disk(): FilesystemAdapter
    {
        $name = config('tenancy.filesystem.disk');
        $disk = $this->filesystem->disk(is_string($name) ? $name : 'tenant');
        assert($disk instanceof FilesystemAdapter);

        return $disk;
    }

    /**
     * URL assinada e temporária no host do tenant. A assinatura cobre o host,
     * então a mesma URL não funciona em outro tenant.
     */
    public function temporaryUrl(string $path, ?int $minutes = null): string
    {
        $ttl = $minutes ?? config('tenancy.filesystem.signed_url_ttl');

        return $this->url->temporarySignedRoute(
            'tenant.files.show',
            now()->addMinutes(is_int($ttl) ? $ttl : 15),
            ['path' => ltrim($path, '/')],
        );
    }
}
