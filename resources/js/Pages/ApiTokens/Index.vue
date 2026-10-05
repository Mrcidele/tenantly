<script setup lang="ts">
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';
import { date } from '@/lib/http';

defineProps<{
    tokens: { id: string; name: string; abilities: string[]; last_used_at: string | null; created_at: string }[];
    abilities: string[];
}>();

const page = usePage<{ flash: { plainTextToken?: string } }>();
const plainTextToken = computed(() => page.props.flash.plainTextToken);
const form = useForm<{ name: string; abilities: string[] }>({ name: '', abilities: ['projects.view'] });
</script>

<template>
    <AppLayout title="Tokens de API">
        <div v-if="plainTextToken" class="mb-6 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm">
            Copie o token agora, ele não será exibido novamente:
            <code class="mt-2 block break-all font-mono">{{ plainTextToken }}</code>
        </div>
        <form class="card mb-6 space-y-3" @submit.prevent="form.post('/settings/api-tokens', { onSuccess: () => form.reset('name') })">
            <div>
                <label class="label" for="name">Nome do token</label>
                <input id="name" v-model="form.name" class="input" required />
                <FormError :message="form.errors.name" />
            </div>
            <div class="flex flex-wrap gap-3">
                <label v-for="ability in abilities" :key="ability" class="flex items-center gap-1 text-sm">
                    <input v-model="form.abilities" type="checkbox" :value="ability" /> {{ ability }}
                </label>
            </div>
            <p class="text-xs text-gray-500">Envie o header <code>X-Tenant</code> com o subdomínio da organização.</p>
            <button class="btn-primary" :disabled="form.processing">Criar token</button>
        </form>
        <div class="card p-0">
            <ul class="divide-y divide-gray-100">
                <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between px-4 py-2 text-sm">
                    <span>{{ token.name }} <span class="text-gray-400">· último uso {{ date(token.last_used_at) }}</span></span>
                    <button class="text-red-600 hover:underline" @click="router.delete(`/settings/api-tokens/${token.id}`)">Revogar</button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
