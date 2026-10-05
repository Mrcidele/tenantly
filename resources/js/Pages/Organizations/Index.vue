<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTenantStore } from '@/stores/tenant';

defineProps<{ organizations: { id: string; name: string; slug: string; role: string }[] }>();
const store = useTenantStore();
</script>

<template>
    <AppLayout title="Organizações">
        <div class="card p-0">
            <ul class="divide-y divide-gray-100">
                <li v-for="organization in organizations" :key="organization.id" class="flex items-center justify-between px-4 py-3">
                    <span>{{ organization.name }} <span class="text-sm text-gray-400">({{ organization.role }})</span></span>
                    <button v-if="organization.id !== store.tenant?.id" class="btn-secondary" @click="router.post(`/organizations/${organization.id}/switch`)">Acessar</button>
                    <span v-else class="text-sm text-gray-500">atual</span>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
