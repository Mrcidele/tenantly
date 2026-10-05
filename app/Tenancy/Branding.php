<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use App\Tenancy\Filesystem\TenantFiles;

/** Branding público do tenant (cores e URL assinada do logo). */
final readonly class Branding
{
    public function __construct(private TenantFiles $files) {}

    /**
     * @return array{primary_color?: string, accent_color?: string, logo_url?: string}
     */
    public function for(Tenant $tenant): array
    {
        $branding = $tenant->branding ?? [];
        $result = [];

        foreach (['primary_color', 'accent_color'] as $key) {
            if (is_string($branding[$key] ?? null)) {
                $result[$key] = $branding[$key];
            }
        }

        if (is_string($branding['logo_path'] ?? null)) {
            $result['logo_url'] = $this->files->temporaryUrl($branding['logo_path'], 60);
        }

        return $result;
    }
}
