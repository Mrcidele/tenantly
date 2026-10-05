<script setup lang="ts">
import { watchEffect } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { applyBranding } from '@/lib/branding';
import type { SharedProps } from '@/types';

defineProps<{ title: string }>();

const page = usePage<SharedProps>();
watchEffect(() => applyBranding(page.props.tenant));
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <img v-if="page.props.tenant?.branding.logo_url" :src="page.props.tenant.branding.logo_url" alt="" class="mx-auto mb-3 h-10" />
                <p class="text-lg font-semibold text-gray-900">{{ page.props.tenant?.name ?? page.props.app.name }}</p>
                <h1 class="mt-1 text-sm text-gray-500">{{ title }}</h1>
            </div>
            <div class="card">
                <FlashMessage />
                <slot />
            </div>
        </div>
    </div>
</template>
