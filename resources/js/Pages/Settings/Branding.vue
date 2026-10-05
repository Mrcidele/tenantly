<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';

const props = defineProps<{ branding: { primary_color?: string; accent_color?: string; logo_url?: string } }>();

const form = useForm<{ primary_color: string; accent_color: string; logo: File | null }>({
    primary_color: props.branding.primary_color ?? '#4f46e5',
    accent_color: props.branding.accent_color ?? '#0ea5e9',
    logo: null,
});

function submit(): void {
    form.transform((data) => ({ ...data, _method: 'put' })).post('/settings/branding', { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Marca">
        <form class="card max-w-lg space-y-4" @submit.prevent="submit">
            <div class="flex gap-6">
                <label class="text-sm">Cor primária <input v-model="form.primary_color" type="color" class="ml-2 align-middle" /></label>
                <label class="text-sm">Cor de destaque <input v-model="form.accent_color" type="color" class="ml-2 align-middle" /></label>
            </div>
            <FormError :message="form.errors.primary_color ?? form.errors.accent_color" />
            <div>
                <label class="label" for="logo">Logo (PNG/SVG até 1 MB)</label>
                <input id="logo" type="file" accept="image/png,image/svg+xml,image/jpeg" @input="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                <FormError :message="form.errors.logo" />
            </div>
            <img v-if="branding.logo_url" :src="branding.logo_url" alt="Logo atual" class="h-12" />
            <button class="btn-primary" :disabled="form.processing">Salvar</button>
        </form>
    </AppLayout>
</template>
