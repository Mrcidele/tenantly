<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FormError from '@/Components/FormError.vue';

const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
</script>

<template>
    <GuestLayout title="Verificação em duas etapas">
        <form class="space-y-4" @submit.prevent="form.post('/two-factor-challenge')">
            <div v-if="!recovery">
                <label class="label" for="code">Código do aplicativo autenticador</label>
                <input id="code" v-model="form.code" inputmode="numeric" autocomplete="one-time-code" class="input" autofocus />
                <FormError :message="form.errors.code" />
            </div>
            <div v-else>
                <label class="label" for="recovery_code">Código de recuperação</label>
                <input id="recovery_code" v-model="form.recovery_code" class="input" />
                <FormError :message="form.errors.recovery_code" />
            </div>
            <div class="flex items-center justify-between">
                <button type="button" class="text-sm text-gray-600 hover:underline" @click="recovery = !recovery">
                    {{ recovery ? 'Usar código do aplicativo' : 'Usar código de recuperação' }}
                </button>
                <button class="btn-primary" :disabled="form.processing">Verificar</button>
            </div>
        </form>
    </GuestLayout>
</template>
