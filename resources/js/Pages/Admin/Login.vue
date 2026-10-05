<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import FormError from '@/Components/FormError.vue';

const form = useForm({ email: '', password: '' });
</script>

<template>
    <Head title="Admin" />
    <div class="flex min-h-screen items-center justify-center bg-slate-900 px-4">
        <form class="card w-full max-w-sm space-y-4" @submit.prevent="form.post('/login')">
            <h1 class="font-semibold">Painel central</h1>
            <input v-model="form.email" type="email" class="input" placeholder="E-mail" required />
            <input v-model="form.password" type="password" class="input" placeholder="Senha" required />
            <FormError :message="form.errors.email" />
            <button class="btn-primary w-full" :disabled="form.processing">Entrar</button>
        </form>
    </div>
</template>
