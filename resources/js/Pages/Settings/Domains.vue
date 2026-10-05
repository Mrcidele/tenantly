<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';
import { date } from '@/lib/http';

defineProps<{
    domains: {
        id: string;
        domain: string;
        status: 'pending' | 'verified' | 'failed';
        is_primary: boolean;
        last_checked_at: string | null;
        failure_reason: string | null;
        txt_name: string;
        txt_value: string;
    }[];
    cnameTarget: string;
}>();

const form = useForm({ domain: '' });
const statusLabel = { pending: 'Pendente', verified: 'Verificado', failed: 'Falhou' } as const;
</script>

<template>
    <AppLayout title="Domínios customizados">
        <form class="card mb-6 flex flex-wrap items-end gap-3" @submit.prevent="form.post('/settings/domains', { onSuccess: () => form.reset() })">
            <div class="min-w-64 flex-1">
                <label class="label" for="domain">Domínio</label>
                <input id="domain" v-model="form.domain" class="input" placeholder="app.suaempresa.com.br" required />
                <FormError :message="form.errors.domain" />
            </div>
            <button class="btn-primary" :disabled="form.processing">Adicionar</button>
        </form>

        <div v-for="domain in domains" :key="domain.id" class="card mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-medium">
                    {{ domain.domain }}
                    <span v-if="domain.is_primary" class="ml-2 rounded bg-brand px-2 py-0.5 text-xs text-brand-contrast">principal</span>
                </p>
                <span class="text-sm" :class="domain.status === 'verified' ? 'text-green-700' : 'text-amber-700'">{{ statusLabel[domain.status] }}</span>
            </div>
            <div v-if="domain.status !== 'verified'" class="mt-3 space-y-1 rounded bg-gray-50 p-3 font-mono text-xs">
                <p>CNAME&nbsp;&nbsp;{{ domain.domain }} → {{ cnameTarget }}</p>
                <p>TXT&nbsp;&nbsp;&nbsp;&nbsp;{{ domain.txt_name }} = {{ domain.txt_value }}</p>
            </div>
            <p v-if="domain.failure_reason" class="mt-2 text-sm text-red-600">{{ domain.failure_reason }}</p>
            <p class="mt-2 text-xs text-gray-500">Última verificação: {{ date(domain.last_checked_at) }}</p>
            <div class="mt-3 flex gap-2">
                <button class="btn-secondary" @click="router.post(`/settings/domains/${domain.id}/verify`, {}, { preserveScroll: true })">Verificar agora</button>
                <button v-if="domain.status === 'verified' && !domain.is_primary" class="btn-secondary" @click="router.post(`/settings/domains/${domain.id}/primary`)">Tornar principal</button>
                <button class="btn-danger" @click="router.delete(`/settings/domains/${domain.id}`)">Remover</button>
            </div>
        </div>
    </AppLayout>
</template>
