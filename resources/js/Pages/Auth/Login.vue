<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FormError from '@/Components/FormError.vue';

const form = useForm({ email: '', password: '', remember: false });
</script>

<template>
    <GuestLayout title="Entrar">
        <form class="space-y-4" @submit.prevent="form.post('/login', { onFinish: () => form.reset('password') })">
            <div>
                <label class="label" for="email">E-mail</label>
                <input id="email" v-model="form.email" type="email" class="input" autocomplete="username" required autofocus />
                <FormError :message="form.errors.email" />
            </div>
            <div>
                <label class="label" for="password">Senha</label>
                <input id="password" v-model="form.password" type="password" class="input" autocomplete="current-password" required />
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="form.remember" type="checkbox" /> Manter conectado
            </label>
            <div class="flex items-center justify-between">
                <Link href="/forgot-password" class="text-sm text-gray-600 hover:underline">Esqueci a senha</Link>
                <button class="btn-primary" :disabled="form.processing">Entrar</button>
            </div>
        </form>
    </GuestLayout>
</template>
