<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { money } from '@/lib/http';

defineProps<{ plans: { id: string; name: string; code: string; price_cents: number; interval: string; features: { feature: string; value: string }[] }[] }>();
</script>

<template>
    <AdminLayout title="Planos">
        <div class="grid gap-4 sm:grid-cols-2">
            <div v-for="plan in plans" :key="plan.id" class="card">
                <p class="font-semibold">{{ plan.name }} <span class="text-sm text-gray-500">({{ plan.code }})</span></p>
                <p class="text-sm">{{ money(plan.price_cents) }} / {{ plan.interval }}</p>
                <ul class="mt-2 text-sm text-gray-600">
                    <li v-for="feature in plan.features" :key="feature.feature">{{ feature.feature }}: {{ feature.value }}</li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>
