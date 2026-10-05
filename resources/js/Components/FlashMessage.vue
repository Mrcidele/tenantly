<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const status = computed(() => page.props.flash.status);
</script>

<template>
    <div v-if="status" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        {{ status }}
    </div>
</template>
