<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Events\TenantProvisioningBilling;
use App\Models\Tenant;
use App\Onboarding\TenantSeeder;
use App\Tenancy\Database\TenantDatabaseStrategyResolver;
use App\Tenancy\Filesystem\TenantFiles;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Provisionamento assíncrono e idempotente: cada passo concluído é gravado
 * em tenants.provisioning e pulado numa nova execução (retry ou reenvio).
 */
final class ProvisionTenant implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    /** @var array<string, string> */
    public const array STEPS = [
        'database' => 'Preparando o banco de dados',
        'storage' => 'Criando o armazenamento',
        'seed' => 'Criando dados iniciais',
        'billing' => 'Iniciando o período de avaliação',
    ];

    public function __construct(public readonly string $tenantId) {}

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(TenantContext $context, TenantDatabaseStrategyResolver $strategies, TenantFiles $files, TenantSeeder $seeder): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);

        if ($tenant->status !== TenantStatus::Provisioning) {
            return;
        }

        $context->run($tenant, function (Tenant $tenant) use ($strategies, $files, $seeder): void {
            $this->step($tenant, 'database', fn () => $strategies->for($tenant)->provision($tenant));
            $this->step($tenant, 'storage', fn () => $files->disk()->put('.keep', ''));
            $this->step($tenant, 'seed', fn () => $seeder->run());
            $this->step($tenant, 'billing', fn () => event(new TenantProvisioningBilling($tenant)));
        });

        $tenant->forceFill([
            'status' => TenantStatus::Active,
            'provisioning' => [...($tenant->provisioning ?? []), 'completed_at' => now()->toIso8601String(), 'error' => null],
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        $tenant?->forceFill([
            'provisioning' => [...($tenant->provisioning ?? []), 'error' => 'Falha no provisionamento. Nossa equipe foi notificada.'],
        ])->save();

        report($exception);
    }

    private function step(Tenant $tenant, string $name, callable $callback): void
    {
        $provisioning = $tenant->provisioning ?? [];
        $done = is_array($provisioning['steps'] ?? null) ? $provisioning['steps'] : [];

        if (isset($done[$name])) {
            return;
        }

        $callback();

        $done[$name] = now()->toIso8601String();
        $tenant->forceFill(['provisioning' => [...$provisioning, 'steps' => $done]])->save();
    }
}
