<?php

declare(strict_types=1);

namespace App\Tenancy\Queue;

use App\Models\Tenant;
use App\Tenancy\Exceptions\MissingTenantContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;

/**
 * Propagação do tenant para filas, aplicada a TODOS os jobs (não depende de
 * cada job declarar um middleware):
 * - ao enfileirar, o tenant ativo é gravado no payload (`tenant_id`);
 * - ao processar, o contexto é restaurado a partir do payload;
 * - ao terminar, o contexto anterior volta (importante na fila `sync`, que
 *   roda dentro da própria request).
 */
final class TenantQueueContext
{
    /** @var list<array{tenant: Tenant|null, user: string|null}> */
    private array $stack = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @return array{tenant_id: string|null}
     */
    public function payload(): array
    {
        return ['tenant_id' => $this->context()->id()];
    }

    public function processing(JobProcessing $event): void
    {
        $context = $this->context();

        $this->stack[] = ['tenant' => $context->current(), 'user' => $context->userId()];

        $tenantId = $event->job->payload()['tenant_id'] ?? null;

        if (! is_string($tenantId)) {
            // Job central: nenhum estado de tenant pode vazar de um job anterior.
            $context->reset();

            return;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            $context->reset();

            // A exceção faz o worker marcar o job como falho (JobFailed restaura a pilha).
            throw new MissingTenantContext("Tenant [{$tenantId}] do job não existe mais.");
        }

        $context->set($tenant);
        $context->setUser(null);
    }

    public function finished(JobProcessed|JobFailed|JobExceptionOccurred|JobReleasedAfterException $event): void
    {
        if ($event instanceof JobExceptionOccurred) {
            return; // JobFailed ou JobReleasedAfterException ainda virão.
        }

        $previous = array_pop($this->stack);

        if ($previous === null) {
            return;
        }

        $context = $this->context();
        $previous['tenant'] !== null ? $context->set($previous['tenant']) : $context->clear();
        $context->setUser($previous['user']);
    }

    private function context(): TenantContext
    {
        return $this->container->make(TenantContext::class);
    }
}
