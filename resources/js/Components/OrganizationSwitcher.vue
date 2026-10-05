<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { http } from '@/lib/http';
import { useTenantStore } from '@/stores/tenant';

interface Organization {
    id: string;
    name: string;
    slug: string;
    role: string;
}

const store = useTenantStore();
const open = ref(false);

const { data, isLoading } = useQuery({
    queryKey: ['organizations'],
    queryFn: () => http<{ organizations: Organization[] }>('/organizations').then((body) => body.organizations),
    enabled: open,
});

function switchTo(organization: Organization): void {
    router.post(`/organizations/${organization.id}/switch`);
}
</script>

<template>
    <div class="relative">
        <button type="button" class="btn-secondary" @click="open = !open">
            {{ store.tenant?.name }}
            <span aria-hidden="true">▾</span>
        </button>
        <div v-if="open" class="absolute left-0 z-10 mt-2 w-64 rounded-md border border-gray-200 bg-white p-2 shadow-lg">
            <p class="px-2 pb-2 text-xs font-semibold text-gray-500 uppercase">Suas organizações</p>
            <p v-if="isLoading" class="px-2 py-1 text-sm text-gray-500">Carregando…</p>
            <button
                v-for="organization in data ?? []"
                :key="organization.id"
                type="button"
                class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100"
                :disabled="organization.id === store.tenant?.id"
                @click="switchTo(organization)"
            >
                <span>{{ organization.name }}</span>
                <span class="text-xs text-gray-400">{{ organization.role }}</span>
            </button>
        </div>
    </div>
</template>
