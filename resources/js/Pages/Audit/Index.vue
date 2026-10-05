<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{
    logs: { id: string; action: string; actor_type: string | null; impersonator_id: string | null; ip_address: string | null; properties: Record<string, unknown> | null; created_at: string }[];
    impersonations: { id: string; reason: string; started_at: string; ended_at: string | null }[];
}>();
</script>

<template>
    <AppLayout title="Auditoria">
        <div v-if="impersonations.length" class="card mb-6">
            <h2 class="mb-2 font-medium">Acessos do suporte</h2>
            <ul class="text-sm">
                <li v-for="item in impersonations" :key="item.id" class="py-1">
                    {{ new Date(item.started_at).toLocaleString('pt-BR') }} — {{ item.reason }}
                    <span class="text-gray-500">{{ item.ended_at ? '(encerrado)' : '(em andamento)' }}</span>
                </li>
            </ul>
        </div>
        <div class="card p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-gray-500">
                    <tr><th class="px-4 py-2">Quando</th><th class="px-4 py-2">Ação</th><th class="px-4 py-2">Quem</th><th class="px-4 py-2">IP</th></tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id" class="border-b border-gray-100">
                        <td class="px-4 py-2 whitespace-nowrap">{{ new Date(log.created_at).toLocaleString('pt-BR') }}</td>
                        <td class="px-4 py-2 font-mono">{{ log.action }}</td>
                        <td class="px-4 py-2">{{ log.actor_type }}<span v-if="log.impersonator_id" class="text-amber-600"> (suporte)</span></td>
                        <td class="px-4 py-2">{{ log.ip_address }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
