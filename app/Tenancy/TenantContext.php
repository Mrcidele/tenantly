<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Audit\AuditLogger;
use App\Models\Tenant;
use App\Tenancy\Contracts\TenancyBootstrapper;
use App\Tenancy\Events\TenantActivated;
use App\Tenancy\Events\TenantDeactivated;
use App\Tenancy\Exceptions\MissingTenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;

/**
 * Tenant ativo da request/job atual. Registrado como `scoped`: o Octane e os
 * workers de fila descartam a instância ao fim de cada request/job, e o
 * middleware ResetTenancy reverte efeitos colaterais globais no início.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?string $userId = null;

    private int $bypassDepth = 0;

    /** @var list<TenancyBootstrapper>|null */
    private ?array $bootstrappers = null;

    public function __construct(
        private readonly Container $container,
        private readonly Dispatcher $events,
    ) {}

    public function set(Tenant $tenant): void
    {
        if ($this->tenant !== null && $this->tenant->getKey() === $tenant->getKey()) {
            return;
        }

        $this->clear();

        $this->tenant = $tenant;

        foreach ($this->bootstrappers() as $bootstrapper) {
            $bootstrapper->bootstrap($tenant);
        }

        $this->events->dispatch(new TenantActivated($tenant));
    }

    public function clear(): void
    {
        if ($this->tenant === null) {
            return;
        }

        $tenant = $this->tenant;
        $this->tenant = null;

        foreach (array_reverse($this->bootstrappers()) as $bootstrapper) {
            $bootstrapper->revert();
        }

        $this->events->dispatch(new TenantDeactivated($tenant));
    }

    /**
     * Volta ao estado central incondicionalmente, inclusive revertendo
     * bootstrappers que possam ter ficado aplicados por uma request anterior
     * no mesmo worker.
     */
    public function reset(): void
    {
        $this->tenant = null;
        $this->userId = null;
        $this->bypassDepth = 0;

        foreach (array_reverse($this->bootstrappers()) as $bootstrapper) {
            $bootstrapper->revert();
        }
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw MissingTenantContext::required();
    }

    public function id(): ?string
    {
        $key = $this->tenant?->getKey();

        return is_string($key) ? $key : null;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Executa o callback com o tenant informado e restaura o contexto anterior.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;

        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous !== null ? $this->set($previous) : $this->clear();
        }
    }

    /**
     * Único caminho para consultas cross-tenant: desliga o global scope,
     * troca para a conexão com BYPASSRLS e grava auditoria com o motivo.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenancy(string $reason, callable $callback): mixed
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('withoutTenancy() exige um motivo para a auditoria.');
        }

        $this->container->make(AuditLogger::class)->record('tenancy.bypass', ['reason' => $reason]);

        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /** Usuário autenticado, exposto ao Postgres como app.user_id. */
    public function setUser(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    /**
     * @return list<TenancyBootstrapper>
     */
    private function bootstrappers(): array
    {
        if ($this->bootstrappers !== null) {
            return $this->bootstrappers;
        }

        /** @var list<class-string<TenancyBootstrapper>> $classes */
        $classes = config('tenancy.bootstrappers', []);

        return $this->bootstrappers = array_map(
            function (string $class): TenancyBootstrapper {
                $bootstrapper = $this->container->make($class);
                assert($bootstrapper instanceof TenancyBootstrapper);

                return $bootstrapper;
            },
            $classes,
        );
    }
}
