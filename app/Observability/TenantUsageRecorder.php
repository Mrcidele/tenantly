<?php

declare(strict_types=1);

namespace App\Observability;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Laravel\Pulse\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pulse: requests (quantidade e duração) e jobs por tenant, para identificar
 * vizinhos barulhentos e decidir quem vai para fila/banco dedicado.
 */
final readonly class TenantUsageRecorder
{
    public function __construct(private Pulse $pulse) {}

    public function register(callable $record, Application $app): void
    {
        $app->afterResolving(Kernel::class, static function (Kernel $kernel) use ($record): void {
            if ($kernel instanceof HttpKernel) {
                $kernel->whenRequestLifecycleIsLongerThan(-1, $record);
            }
        });

        $app->make('events')->listen(JobProcessed::class, function () use ($app): void {
            $tenantId = $app->make(TenantContext::class)->id();

            if ($tenantId !== null) {
                $this->pulse->record('tenant_job', $tenantId)->count();
            }
        });
    }

    public function record(CarbonImmutable|DateTimeInterface $startedAt, Request $request, Response $response): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            return;
        }

        $duration = (int) round((microtime(true) - (float) $startedAt->format('U.u')) * 1000);

        $this->pulse->record('tenant_request', $tenantId, $duration, CarbonImmutable::instance($startedAt)->getTimestamp())
            ->count()
            ->avg()
            ->max();
    }
}
