<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\SubscriptionStateMachine;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Agendado: fim de trial sem pagamento → inadimplente; fim do período de
 * graça → suspensa (modo somente leitura).
 */
final class EnforceBillingDeadlines extends Command
{
    protected $signature = 'billing:enforce-deadlines';

    protected $description = 'Aplica fim de trial e de período de graça';

    public function handle(TenantContext $context, SubscriptionStateMachine $states): int
    {
        /** @var list<array{tenant_id: string}> $due */
        $due = $context->withoutTenancy('billing:enforce-deadlines: listar assinaturas vencidas', fn (): array => Subscription::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', SubscriptionStatus::Trialing)->where('trial_ends_at', '<=', now()))
                ->orWhere(fn ($q) => $q->where('status', SubscriptionStatus::PastDue)->where('grace_ends_at', '<=', now())))
            ->get(['tenant_id'])
            ->toArray());

        foreach ($due as $row) {
            $tenant = Tenant::query()->find($row['tenant_id']);

            if ($tenant === null) {
                continue;
            }

            $context->run($tenant, function () use ($states): void {
                $subscription = Subscription::query()->firstOrFail();

                if ($subscription->status === SubscriptionStatus::Trialing) {
                    $paid = $subscription->invoices()->where('status', InvoiceStatus::Paid)->exists();
                    $states->transition($subscription, $paid ? SubscriptionStatus::Active : SubscriptionStatus::PastDue, 'Fim do período de avaliação');
                } elseif ($subscription->status === SubscriptionStatus::PastDue) {
                    $states->transition($subscription, SubscriptionStatus::Suspended, 'Fim do período de graça');
                }
            });

            $this->line("Tenant {$tenant->slug} atualizado.");
        }

        return self::SUCCESS;
    }
}
