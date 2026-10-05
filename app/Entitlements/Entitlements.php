<?php

declare(strict_types=1);

namespace App\Entitlements;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Enums\SubscriptionStatus;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;

/**
 * O que o tenant ativo pode fazer, segundo o plano da assinatura e ajustes
 * manuais (tenants.limit_overrides, editados no painel central).
 */
final class Entitlements
{
    /** @var array<string, array{subscription: Subscription|null, features: array<string, string>}> */
    private array $resolved = [];

    public function __construct(
        private readonly Container $container,
        private readonly UsageMeter $meter,
    ) {}

    public function can(Feature $feature): bool
    {
        $override = $this->context()->get()->limit_overrides[$feature->value] ?? null;

        if (is_bool($override)) {
            return $override;
        }

        return ($this->state()['features'][$feature->value] ?? 'false') === 'true';
    }

    /** null = ilimitado. */
    public function limit(Limit $limit): ?int
    {
        $override = $this->context()->get()->limit_overrides[$limit->value] ?? null;
        $value = is_int($override) ? $override : (int) ($this->state()['features'][$limit->value] ?? 0);

        return $value < 0 ? null : $value;
    }

    public function usage(Limit $limit): int
    {
        return match ($limit) {
            Limit::Users => Membership::query()->count() + Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
            Limit::Projects => Project::query()->count(),
            Limit::StorageMb => intdiv($this->meter->get($this->tenantId(), Limit::StorageMb), 1024 * 1024),
            Limit::ApiCallsPerMonth => $this->meter->get($this->tenantId(), Limit::ApiCallsPerMonth),
        };
    }

    public function allows(Limit $limit, int $adding = 1): bool
    {
        $max = $this->limit($limit);

        return $max === null || $this->usage($limit) + $adding <= $max;
    }

    /** @throws LimitExceeded */
    public function ensure(Limit $limit, int $adding = 1): void
    {
        if (! $this->allows($limit, $adding)) {
            throw new LimitExceeded($limit, $this->limit($limit) ?? 0);
        }
    }

    /**
     * Limites que o uso atual excederia no plano informado (para downgrade).
     *
     * @return array<string, int>
     */
    public function violationsFor(Plan $plan): array
    {
        $features = $plan->featureMap();
        $violations = [];

        foreach ([Limit::Users, Limit::Projects, Limit::StorageMb] as $limit) {
            $max = (int) ($features[$limit->value] ?? 0);
            $usage = $this->usage($limit);

            if ($max >= 0 && $usage > $max) {
                $violations[$limit->value] = $usage;
            }
        }

        return $violations;
    }

    /** Assinatura suspensa/cancelada: somente leitura. */
    public function writable(): bool
    {
        return $this->subscription()?->status->allowsWrites() ?? true;
    }

    public function subscription(): ?Subscription
    {
        return $this->state()['subscription'];
    }

    public function status(): ?SubscriptionStatus
    {
        return $this->subscription()?->status;
    }

    public function flush(): void
    {
        $this->resolved = [];
    }

    /**
     * @return array{subscription: Subscription|null, features: array<string, string>}
     */
    private function state(): array
    {
        $tenantId = $this->tenantId();

        if (! isset($this->resolved[$tenantId])) {
            $subscription = Subscription::query()->with('plan.features')->first();

            $this->resolved[$tenantId] = [
                'subscription' => $subscription,
                'features' => $subscription?->plan->featureMap() ?? [],
            ];
        }

        return $this->resolved[$tenantId];
    }

    private function tenantId(): string
    {
        return $this->context()->get()->id;
    }

    private function context(): TenantContext
    {
        return $this->container->make(TenantContext::class);
    }
}
