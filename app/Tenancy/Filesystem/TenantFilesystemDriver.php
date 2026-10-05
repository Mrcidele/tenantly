<?php

declare(strict_types=1);

namespace App\Tenancy\Filesystem;

use App\Tenancy\Exceptions\MissingTenantContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;

/**
 * Driver "tenant": o mesmo disco base (local/s3) com raiz "tenants/{id}".
 * Sem tenant ativo o disco não pode ser montado.
 */
final readonly class TenantFilesystemDriver
{
    public function __construct(private Container $container) {}

    public function __invoke(): Filesystem
    {
        $tenantId = $this->container->make(TenantContext::class)->id() ?? throw MissingTenantContext::required();

        $baseName = config('tenancy.filesystem.base_disk');
        $base = is_string($baseName) ? config("filesystems.disks.{$baseName}") : null;

        if (! is_array($base)) {
            throw new RuntimeException('Disco base do tenant não configurado.');
        }

        /** @var array<string, mixed> $base */
        $root = is_string($base['root'] ?? null) ? $base['root'] : '';

        $base['root'] = ($base['driver'] ?? null) === 'local'
            ? rtrim($root, '/').'/tenants/'.$tenantId
            : trim($root.'/tenants/'.$tenantId, '/');
        $base['serve'] = false;

        return $this->container->make(FilesystemManager::class)->build($base);
    }
}
