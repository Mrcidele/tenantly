<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Settings;

use App\Audit\AuditLogger;
use App\Entitlements\Entitlements;
use App\Entitlements\UsageMeter;
use App\Enums\Limit;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Tenancy\Branding;
use App\Tenancy\Filesystem\TenantFiles;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

final class BrandingController extends Controller
{
    public function edit(TenantContext $context, Branding $branding): Response
    {
        $this->authorize(Permission::ManageSettings->value);

        return Inertia::render('Settings/Branding', ['branding' => $branding->for($context->get())]);
    }

    public function update(Request $request, TenantContext $context, TenantFiles $files, Entitlements $entitlements, UsageMeter $meter, AuditLogger $audit): RedirectResponse
    {
        $this->authorize(Permission::ManageSettings->value);

        $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:1024'],
        ]);

        $tenant = $context->get();
        $branding = [
            ...($tenant->branding ?? []),
            'primary_color' => $request->string('primary_color')->lower()->value(),
            'accent_color' => $request->string('accent_color')->lower()->value(),
        ];

        $logo = $request->file('logo');

        if ($logo instanceof UploadedFile) {
            $size = (int) $logo->getSize();
            $entitlements->ensure(Limit::StorageMb, (int) ceil($size / 1024 / 1024));

            $path = $files->disk()->putFileAs('branding', $logo, 'logo.'.$logo->extension());
            $branding['logo_path'] = is_string($path) ? $path : null;
            $meter->increment($tenant->id, Limit::StorageMb, $size);
        }

        $tenant->forceFill(['branding' => $branding])->save();
        $audit->record('settings.branding_updated');

        return back()->with('status', 'Marca atualizada.');
    }
}
