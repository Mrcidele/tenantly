<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useQuery } from '@tanstack/vue-query';
import { http } from '@/lib/http';

interface Provisioning {
    status: string;
    ready: boolean;
    error: string | null;
    steps: { key: string; label: string; done: boolean }[];
}

const props = defineProps<{ tenant: { id: string; name: string }; provisioning: Provisioning }>();

const { data } = useQuery({
    queryKey: ['provisioning', props.tenant.id],
    queryFn: () => http<Provisioning>(`/signup/${props.tenant.id}/status`),
    initialData: props.provisioning,
    refetchInterval: (query) => (query.state.data?.ready || query.state.data?.error ? false : 1500),
});

const state = computed(() => data.value ?? props.provisioning);

function enter(): void {
    router.post(`/signup/${props.tenant.id}/continue`);
}
</script>

<template>
    <Head title="Preparando sua organização" />
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
        <div class="card w-full max-w-md">
            <h1 class="text-lg font-semibold">Preparando {{ tenant.name }}</h1>
            <ul class="mt-4 space-y-2">
                <li v-for="step in state.steps" :key="step.key" class="flex items-center gap-2 text-sm">
                    <span :class="step.done ? 'text-green-600' : 'text-gray-400'">{{ step.done ? '✓' : '○' }}</span>
                    {{ step.label }}
                </li>
            </ul>
            <p v-if="state.error" class="mt-4 text-sm text-red-600">{{ state.error }}</p>
            <button v-if="state.ready" class="btn-primary mt-6 w-full" @click="enter">Entrar na organização</button>
        </div>
    </div>
</template>
