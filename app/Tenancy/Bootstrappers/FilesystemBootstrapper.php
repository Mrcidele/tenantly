<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Models\Tenant;
use App\Tenancy\Contracts\TenancyBootstrapper;
use Illuminate\Filesystem\FilesystemManager;

/**
 * O disco "tenant" é montado sob demanda (driver registrado no
 * TenancyServiceProvider) com raiz "tenants/{id}". Trocar de tenant só
 * descarta a instância em cache.
 */
final readonly class FilesystemBootstrapper implements TenancyBootstrapper
{
    public function __construct(private FilesystemManager $filesystem) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->forget();
    }

    public function revert(): void
    {
        $this->forget();
    }

    private function forget(): void
    {
        $disk = config('tenancy.filesystem.disk');

        $this->filesystem->forgetDisk(is_string($disk) ? $disk : 'tenant');
    }
}
