<?php

declare(strict_types=1);

namespace App\Livewire\Pulse;

use App\Models\Tenant;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/** Card do Pulse: tenants com mais requests, tempo médio/máximo e jobs. */
#[Lazy]
final class TenantUsage extends Card
{
    public function render(): Renderable
    {
        [$rows, $time, $runAt] = $this->remember(function (): array {
            $requests = $this->aggregate('tenant_request', ['count', 'avg', 'max'], 'count', limit: 10);
            $jobs = $this->aggregate('tenant_job', 'count', limit: 50)->keyBy('key');
            $tenants = Tenant::query()->whereIn('id', $requests->pluck('key'))->pluck('name', 'id');

            $rows = [];

            foreach ($requests as $row) {
                $data = (array) $row;
                $key = is_string($data['key'] ?? null) ? $data['key'] : '';
                $job = (array) ($jobs->get($key) ?? []);

                $rows[] = (object) [
                    'tenant' => $tenants[$key] ?? $key,
                    'requests' => self::int($data['count'] ?? 0),
                    'avg' => self::int($data['avg'] ?? 0),
                    'max' => self::int($data['max'] ?? 0),
                    'jobs' => self::int($job['count'] ?? 0),
                ];
            }

            return $rows;
        });

        return View::make('livewire.pulse.tenant-usage', ['rows' => $rows, 'time' => $time, 'runAt' => $runAt]);
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
