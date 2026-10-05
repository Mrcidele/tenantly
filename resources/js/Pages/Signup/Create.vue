<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { refDebounced } from '@/lib/debounce';
import { http } from '@/lib/http';
import FormError from '@/Components/FormError.vue';

const props = defineProps<{ centralDomain: string }>();

const form = useForm({
    organization: '',
    subdomain: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const subdomain = computed(() => form.subdomain.trim().toLowerCase());
const debounced = refDebounced(subdomain, 400);

const availability = useQuery({
    queryKey: ['subdomain', debounced],
    queryFn: () => http<{ available: boolean; message: string | null }>(`/signup/check-subdomain?subdomain=${encodeURIComponent(debounced.value)}`),
    enabled: computed(() => debounced.value.length >= 3),
});
</script>

<template>
    <Head title="Criar organização" />
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
        <form class="card w-full max-w-lg space-y-4" @submit.prevent="form.post('/signup')">
            <h1 class="text-xl font-semibold">Criar organização</h1>
            <div>
                <label class="label" for="organization">Nome da organização</label>
                <input id="organization" v-model="form.organization" class="input" required />
                <FormError :message="form.errors.organization" />
            </div>
            <div>
                <label class="label" for="subdomain">Endereço</label>
                <div class="flex items-center gap-2">
                    <input id="subdomain" v-model="form.subdomain" class="input" required />
                    <span class="text-sm whitespace-nowrap text-gray-500">.{{ props.centralDomain }}</span>
                </div>
                <p v-if="availability.data.value" class="mt-1 text-sm" :class="availability.data.value.available ? 'text-green-600' : 'text-red-600'">
                    {{ availability.data.value.available ? 'Disponível' : availability.data.value.message }}
                </p>
                <FormError :message="form.errors.subdomain" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Seu nome</label>
                    <input id="name" v-model="form.name" class="input" required />
                    <FormError :message="form.errors.name" />
                </div>
                <div>
                    <label class="label" for="email">E-mail</label>
                    <input id="email" v-model="form.email" type="email" class="input" required />
                    <FormError :message="form.errors.email" />
                </div>
                <div>
                    <label class="label" for="password">Senha</label>
                    <input id="password" v-model="form.password" type="password" class="input" required />
                    <FormError :message="form.errors.password" />
                </div>
                <div>
                    <label class="label" for="password_confirmation">Confirmar senha</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" required />
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="form.terms" type="checkbox" /> Aceito os termos de uso e a política de privacidade
            </label>
            <FormError :message="form.errors.terms" />
            <button class="btn-primary w-full" :disabled="form.processing">Criar organização</button>
        </form>
    </div>
</template>
