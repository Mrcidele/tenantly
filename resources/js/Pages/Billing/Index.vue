<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date, http, money } from '@/lib/http';

interface Plan {
    id: string;
    code: string;
    name: string;
    price_cents: number;
    interval: 'month' | 'year';
}

const props = defineProps<{
    subscription: { status: string; status_label: string; plan: Plan; trial_ends_at: string | null; current_period_ends_at: string | null; grace_ends_at: string | null } | null;
    usage: { key: string; label: string; used: number; limit: number | null }[];
    plans: Plan[];
    invoices: { id: string; amount_cents: number; status: string; method: string | null; description: string; payment_url: string | null; due_at: string | null; paid_at: string | null }[];
}>();

const selected = ref<Plan | null>(null);

const preview = useQuery({
    queryKey: ['billing-preview', selected],
    queryFn: () => http<{ net_cents: number; credit_cents: number; charge_cents: number }>(`/billing/preview?plan_id=${selected.value?.id}`),
    enabled: () => selected.value !== null && props.subscription?.status !== 'trialing',
});

function confirmChange(): void {
    if (selected.value) {
        router.post('/billing/plan', { plan_id: selected.value.id }, { onSuccess: () => (selected.value = null) });
    }
}

function cancel(): void {
    if (confirm('Cancelar a assinatura? A organização ficará somente leitura ao fim do período.')) {
        router.post('/billing/cancel');
    }
}
</script>

<template>
    <AppLayout title="Assinatura">
        <div v-if="subscription" class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Plano atual</p>
                    <p class="text-xl font-semibold">{{ subscription.plan.name }}</p>
                </div>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-sm">{{ subscription.status_label }}</span>
            </div>
            <p v-if="subscription.status === 'trialing'" class="mt-3 text-sm text-gray-600">Avaliação até {{ date(subscription.trial_ends_at) }}.</p>
            <p v-if="subscription.status === 'past_due'" class="mt-3 text-sm text-amber-700">Pagamento pendente. Regularize até {{ date(subscription.grace_ends_at) }} para evitar a suspensão.</p>
            <p v-if="subscription.status === 'suspended'" class="mt-3 text-sm text-red-700">Assinatura suspensa: modo somente leitura.</p>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <div v-for="item in usage" :key="item.key" class="card">
                <p class="text-sm text-gray-500">{{ item.label }}</p>
                <p class="text-lg font-semibold">{{ item.used }} <span class="text-sm text-gray-400">/ {{ item.limit ?? '∞' }}</span></p>
            </div>
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <button
                v-for="plan in plans"
                :key="plan.id"
                type="button"
                class="card text-left hover:border-brand"
                :class="{ 'border-brand ring-2 ring-brand/30': selected?.id === plan.id }"
                :disabled="plan.id === subscription?.plan.id"
                @click="selected = plan"
            >
                <p class="font-semibold">{{ plan.name }}</p>
                <p class="text-sm text-gray-600">{{ money(plan.price_cents) }} / {{ plan.interval === 'year' ? 'ano' : 'mês' }}</p>
            </button>
        </div>

        <div v-if="selected" class="card mb-6 flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm">
                Trocar para <strong>{{ selected.name }}</strong>.
                <template v-if="preview.data.value">
                    {{ preview.data.value.net_cents >= 0 ? 'Cobrança proporcional agora:' : 'Crédito no próximo ciclo:' }}
                    <strong>{{ money(Math.abs(preview.data.value.net_cents)) }}</strong>
                </template>
            </p>
            <button class="btn-primary" @click="confirmChange">Confirmar</button>
        </div>

        <div class="card p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-gray-500">
                    <tr><th class="px-4 py-2">Descrição</th><th class="px-4 py-2">Valor</th><th class="px-4 py-2">Vencimento</th><th class="px-4 py-2">Status</th><th /></tr>
                </thead>
                <tbody>
                    <tr v-for="invoice in invoices" :key="invoice.id" class="border-b border-gray-100">
                        <td class="px-4 py-2">{{ invoice.description }}</td>
                        <td class="px-4 py-2">{{ money(invoice.amount_cents) }}</td>
                        <td class="px-4 py-2">{{ date(invoice.due_at) }}</td>
                        <td class="px-4 py-2">{{ invoice.status }}</td>
                        <td class="px-4 py-2 text-right">
                            <a v-if="invoice.payment_url && invoice.status !== 'paid'" :href="invoice.payment_url" target="_blank" rel="noopener" class="text-brand hover:underline">Pagar (Pix/boleto)</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button v-if="subscription && subscription.status !== 'canceled'" class="btn-secondary mt-6" @click="cancel">Cancelar assinatura</button>
    </AppLayout>
</template>
