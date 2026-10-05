<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = app(TenantContext::class)->current();
        $user = $request->user();
        $membership = $user instanceof User ? $user->membership() : null;

        return [
            ...parent::share($request),
            'app' => ['name' => config('app.name')],
            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $tenant->branding ?? [],
            ],
            'auth' => [
                'user' => $user instanceof User ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null,
                'role' => $membership?->role->value,
                'permissions' => $membership === null ? [] : array_map(
                    static fn (Permission $permission): string => $permission->value,
                    $membership->role->permissions(),
                ),
                'impersonating' => $request->hasSession() && $request->session()->has('impersonation_id'),
            ],
            'flash' => [
                'status' => $request->hasSession() ? $request->session()->get('status') : null,
            ],
        ];
    }
}
