<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import FormError from '@/Components/FormError.vue';

const props = defineProps<{ invitation: { email: string; role: string }; action: string }>();
const form = useForm({ name: '', password: '' });
</script>

<template>
    <GuestLayout title="Aceitar convite">
        <p class="mb-4 text-sm text-gray-600">
            Convite para <strong>{{ invitation.email }}</strong> como {{ invitation.role }}.
            Se já tem conta em outra organização, informe apenas a senha dela.
        </p>
        <form class="space-y-4" @submit.prevent="form.post(props.action)">
            <div>
                <label class="label" for="name">Seu nome (conta nova)</label>
                <input id="name" v-model="form.name" class="input" />
                <FormError :message="form.errors.name" />
            </div>
            <div>
                <label class="label" for="password">Senha</label>
                <input id="password" v-model="form.password" type="password" class="input" required />
                <FormError :message="form.errors.password ?? (form.errors as Record<string, string>).token" />
            </div>
            <button class="btn-primary w-full" :disabled="form.processing">Entrar na organização</button>
        </form>
    </GuestLayout>
</template>
