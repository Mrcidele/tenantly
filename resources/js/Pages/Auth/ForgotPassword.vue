<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FormError from '@/Components/FormError.vue';

const form = useForm({ email: '' });
</script>

<template>
    <GuestLayout title="Redefinir senha">
        <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
            <div>
                <label class="label" for="email">E-mail</label>
                <input id="email" v-model="form.email" type="email" class="input" required />
                <FormError :message="form.errors.email" />
            </div>
            <button class="btn-primary w-full" :disabled="form.processing">Enviar link</button>
        </form>
    </GuestLayout>
</template>
