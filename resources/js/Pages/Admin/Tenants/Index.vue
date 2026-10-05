<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { date } from '@/lib/http';

const props = defineProps<{
    tenants: { data: { id: string; name: string; slug: string; status: string; created_at: string }[]; links: { url: string | null; label: string; active: boolean }[] };
    q: string;
}>();

const search = ref(props.q);
</script>

<template>
    <AdminLayout title="Tenants">
        <form class="mb-4" @submit.prevent="router.get('/tenants', { q: search }, { preserveState: true })">
            <input v-model="search" class="input max-w-sm" placeholder="Buscar por nome ou subdomínio" />
        </form>
        <div class="card p-0">
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="border-b border-gray-100">
                        <td class="px-4 py-2"><Link :href="`/tenants/${tenant.id}`" class="text-brand hover:underline">{{ tenant.name }}</Link></td>
                        <td class="px-4 py-2 text-gray-500">{{ tenant.slug }}</td>
                        <td class="px-4 py-2">{{ tenant.status }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ date(tenant.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex gap-2">
            <template v-for="link in tenants.links" :key="link.label">
                <Link v-if="link.url" :href="link.url" class="rounded px-2 py-1 text-sm" :class="link.active ? 'bg-slate-900 text-white' : 'bg-white'" v-html="link.label" />
            </template>
        </div>
    </AdminLayout>
</template>
