<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FormError from '@/Components/FormError.vue';

const form = useForm({ password: '' });
</script>

<template>
    <GuestLayout title="Confirme sua senha">
        <form class="space-y-4" @submit.prevent="form.post('/user/confirm-password')">
            <div>
                <label class="label" for="password">Senha</label>
                <input id="password" v-model="form.password" type="password" class="input" required autofocus />
                <FormError :message="form.errors.password" />
            </div>
            <button class="btn-primary w-full" :disabled="form.processing">Confirmar</button>
        </form>
    </GuestLayout>
</template>
