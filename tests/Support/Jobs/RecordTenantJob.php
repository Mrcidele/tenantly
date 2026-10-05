<?php

declare(strict_types=1);

namespace Tests\Support\Jobs;

use App\Models\Project;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

final class RecordTenantJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $label) {}

    public function handle(TenantContext $context): void
    {
        Cache::store('central')->put('job:'.$this->label, [
            'tenant' => $context->id(),
            'projects' => $context->check() ? Project::query()->pluck('name')->all() : null,
            'url' => url('/x'),
        ]);
    }
}
