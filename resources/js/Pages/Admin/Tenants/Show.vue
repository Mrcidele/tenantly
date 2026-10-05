<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FormError from '@/Components/FormError.vue';

const props = defineProps<{
    tenant: { id: string; name: string; slug: string; status: string; limit_overrides: Record<string, number | boolean> | null };
    subscription: { status: string; plan: string; current_period_ends_at: string | null } | null;
    members: { user_id: string; name: string; email: string; role: string }[];
    usage: Record<string, { used: number; limit: number | null }>;
}>();

const reason = ref('');
const limits = useForm<{ overrides: Record<string, number | null>; reason: string }>({
    overrides: Object.fromEntries(Object.keys(props.usage).map((key) => [key, (props.tenant.limit_overrides?.[key] as number | undefined) ?? null])),
    reason: '',
});

function action(name: 'suspend' | 'reactivate'): void {
    router.post(`/tenants/${props.tenant.id}/${name}`, { reason: reason.value }, { preserveScroll: true });
}

function impersonate(userId: string): void {
    const why = prompt('Motivo da impersonação (ex.: número do chamado):');
    if (why) {
        router.post(`/tenants/${props.tenant.id}/impersonate`, { user_id: userId, reason: why });
    }
}
</script>

<template>
    <AdminLayout :title="tenant.name">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card space-y-2 text-sm">
                <p><strong>Subdomínio:</strong> {{ tenant.slug }}</p>
                <p><strong>Status:</strong> {{ tenant.status }}</p>
                <p><strong>Plano:</strong> {{ subscription?.plan ?? '—' }} ({{ subscription?.status ?? 'sem assinatura' }})</p>
                <input v-model="reason" class="input mt-3" placeholder="Motivo (obrigatório)" />
                <div class="flex gap-2">
                    <button v-if="tenant.status === 'active'" class="btn-danger" @click="action('suspend')">Suspender</button>
                    <button v-else class="btn-primary" @click="action('reactivate')">Reativar</button>
                </div>
            </div>
            <form class="card space-y-2 text-sm" @submit.prevent="limits.post(`/tenants/${tenant.id}/limits`, { preserveScroll: true })">
                <h2 class="font-medium">Uso e limites</h2>
                <div v-for="(item, key) in usage" :key="key" class="flex items-center justify-between gap-2">
                    <span>{{ key }}: {{ item.used }} / {{ item.limit ?? '∞' }}</span>
                    <input v-model.number="limits.overrides[key]" type="number" min="-1" class="input w-28" placeholder="ajuste" />
                </div>
                <input v-model="limits.reason" class="input" placeholder="Motivo do ajuste" />
                <FormError :message="limits.errors.reason ?? limits.errors.overrides" />
                <button class="btn-secondary">Salvar ajustes</button>
            </form>
        </div>
        <div class="card mt-6 p-0">
            <table class="w-full text-sm">
                <tr v-for="member in members" :key="member.user_id" class="border-b border-gray-100">
                    <td class="px-4 py-2">{{ member.name }} <span class="text-gray-500">{{ member.email }}</span></td>
                    <td class="px-4 py-2">{{ member.role }}</td>
                    <td class="px-4 py-2 text-right"><button class="text-amber-700 hover:underline" @click="impersonate(member.user_id)">Impersonar</button></td>
                </tr>
            </table>
        </div>
    </AdminLayout>
</template>
