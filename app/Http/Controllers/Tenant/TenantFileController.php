<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Tenancy\Filesystem\TenantFiles;
use League\Flysystem\PathTraversalDetected;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TenantFileController extends Controller
{
    public function show(TenantFiles $files, string $path): StreamedResponse
    {
        $disk = $files->disk();

        try {
            abort_unless($disk->exists($path), 404);
        } catch (PathTraversalDetected) {
            abort(404);
        }

        return $disk->response($path);
    }
}
