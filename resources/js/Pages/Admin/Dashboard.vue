<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { money } from '@/lib/http';

defineProps<{
    metrics: {
        mrr_cents: number;
        active_tenants: number;
        churn_rate: number;
        by_plan: { plan: string; tenants: number; mrr_cents: number }[];
        by_status: Record<string, number>;
    };
}>();
</script>

<template>
    <AdminLayout title="Métricas">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card"><p class="text-sm text-gray-500">MRR</p><p class="text-3xl font-semibold">{{ money(metrics.mrr_cents) }}</p></div>
            <div class="card"><p class="text-sm text-gray-500">Tenants ativos</p><p class="text-3xl font-semibold">{{ metrics.active_tenants }}</p></div>
            <div class="card"><p class="text-sm text-gray-500">Churn (30 dias)</p><p class="text-3xl font-semibold">{{ metrics.churn_rate }}%</p></div>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="card">
                <h2 class="mb-2 font-medium">Por plano</h2>
                <table class="w-full text-sm">
                    <tr v-for="row in metrics.by_plan" :key="row.plan" class="border-b border-gray-100">
                        <td class="py-1">{{ row.plan }}</td><td>{{ row.tenants }} tenants</td><td class="text-right">{{ money(row.mrr_cents) }}</td>
                    </tr>
                </table>
            </div>
            <div class="card">
                <h2 class="mb-2 font-medium">Por status</h2>
                <ul class="text-sm">
                    <li v-for="(count, status) in metrics.by_status" :key="status">{{ status }}: {{ count }}</li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>
