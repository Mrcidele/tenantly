<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';
import { http } from '@/lib/http';

const props = defineProps<{ twoFactorEnabled: boolean; twoFactorConfirmed: boolean }>();

const qrCode = ref<string | null>(null);
const recoveryCodes = ref<string[]>([]);
const confirmForm = useForm({ code: '' });
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

function enable(): void {
    router.post('/user/two-factor-authentication', {}, {
        preserveScroll: true,
        onSuccess: async () => {
            qrCode.value = (await http<{ svg: string }>('/user/two-factor-qr-code')).svg;
        },
    });
}

function confirm2fa(): void {
    confirmForm.post('/user/confirmed-two-factor-authentication', {
        preserveScroll: true,
        onSuccess: async () => {
            qrCode.value = null;
            recoveryCodes.value = await http<string[]>('/user/two-factor-recovery-codes');
        },
    });
}

function disable(): void {
    router.delete('/user/two-factor-authentication', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Segurança">
        <div class="card mb-6">
            <h2 class="mb-2 font-medium">Verificação em duas etapas</h2>
            <template v-if="props.twoFactorConfirmed">
                <p class="mb-3 text-sm text-green-700">Ativa.</p>
                <ul v-if="recoveryCodes.length" class="mb-3 grid grid-cols-2 gap-1 font-mono text-sm">
                    <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
                </ul>
                <button class="btn-secondary" @click="disable">Desativar</button>
            </template>
            <template v-else>
                <div v-if="qrCode" class="space-y-3">
                    <div v-html="qrCode" />
                    <input v-model="confirmForm.code" class="input w-48" placeholder="Código de 6 dígitos" />
                    <FormError :message="confirmForm.errors.code" />
                    <button class="btn-primary" @click="confirm2fa">Confirmar</button>
                </div>
                <button v-else class="btn-primary" @click="enable">Ativar</button>
            </template>
        </div>

        <form class="card space-y-3" @submit.prevent="passwordForm.put('/user/password', { preserveScroll: true, onSuccess: () => passwordForm.reset() })">
            <h2 class="font-medium">Alterar senha</h2>
            <input v-model="passwordForm.current_password" type="password" class="input" placeholder="Senha atual" />
            <input v-model="passwordForm.password" type="password" class="input" placeholder="Nova senha" />
            <input v-model="passwordForm.password_confirmation" type="password" class="input" placeholder="Confirmar nova senha" />
            <FormError :message="passwordForm.errors.current_password ?? passwordForm.errors.password" />
            <button class="btn-primary" :disabled="passwordForm.processing">Salvar</button>
        </form>
    </AppLayout>
</template>
