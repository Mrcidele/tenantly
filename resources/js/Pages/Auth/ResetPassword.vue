<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FormError from '@/Components/FormError.vue';

const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <GuestLayout title="Nova senha">
        <form class="space-y-4" @submit.prevent="form.post('/reset-password')">
            <div>
                <label class="label" for="email">E-mail</label>
                <input id="email" v-model="form.email" type="email" class="input" required />
                <FormError :message="form.errors.email" />
            </div>
            <div>
                <label class="label" for="password">Nova senha</label>
                <input id="password" v-model="form.password" type="password" class="input" required />
                <FormError :message="form.errors.password" />
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirmar senha</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" required />
            </div>
            <button class="btn-primary w-full" :disabled="form.processing">Salvar</button>
        </form>
    </GuestLayout>
</template>
