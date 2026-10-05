<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';
import { date } from '@/lib/http';
import type { SharedProps } from '@/types';

defineProps<{
    exports: { id: string; status: string; size_bytes: number | null; completed_at: string | null; expires_at: string | null; created_at: string }[];
    retentionDays: number;
}>();

const page = usePage<SharedProps>();
const deletion = useForm({ password: '', confirm: '' });
</script>

<template>
    <AppLayout title="Dados e privacidade">
        <div class="card mb-6">
            <h2 class="mb-2 font-medium">Exportar dados (LGPD)</h2>
            <p class="mb-3 text-sm text-gray-600">Gera um pacote .zip com todos os dados da organização.</p>
            <button class="btn-primary" @click="router.post('/settings/data/exports')">Solicitar exportação</button>
            <ul class="mt-4 text-sm">
                <li v-for="item in exports" :key="item.id" class="flex justify-between border-t border-gray-100 py-2">
                    <span>{{ date(item.created_at) }} · {{ item.status }}</span>
                    <a v-if="item.status === 'ready'" :href="`/settings/data/exports/${item.id}`" class="text-brand hover:underline">Baixar (até {{ date(item.expires_at) }})</a>
                </li>
            </ul>
        </div>
        <form class="card border-red-200" @submit.prevent="deletion.post('/settings/organization/delete')">
            <h2 class="mb-2 font-medium text-red-700">Excluir organização</h2>
            <p class="mb-3 text-sm text-gray-600">
                O acesso é bloqueado imediatamente e os dados são apagados definitivamente após {{ retentionDays }} dias.
                Digite <strong>{{ page.props.tenant?.slug }}</strong> e sua senha para confirmar.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                <input v-model="deletion.confirm" class="input" placeholder="Endereço da organização" />
                <input v-model="deletion.password" type="password" class="input" placeholder="Sua senha" />
            </div>
            <FormError :message="deletion.errors.password" />
            <button class="btn-danger mt-3" :disabled="deletion.processing">Excluir organização</button>
        </form>
    </AppLayout>
</template>
