<script setup lang="ts">
import { watchEffect } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import OrganizationSwitcher from '@/Components/OrganizationSwitcher.vue';
import { applyBranding } from '@/lib/branding';
import { useTenantStore } from '@/stores/tenant';

defineProps<{ title: string }>();

const store = useTenantStore();

watchEffect(() => applyBranding(store.tenant));

const nav = [
    { href: '/', label: 'Início', permission: null },
    { href: '/projects', label: 'Projetos', permission: 'projects.view' },
    { href: '/members', label: 'Membros', permission: 'members.view' },
    { href: '/settings/domains', label: 'Domínios', permission: 'domains.manage' },
    { href: '/settings/branding', label: 'Marca', permission: 'settings.manage' },
    { href: '/settings/api-tokens', label: 'API', permission: 'api-tokens.manage' },
    { href: '/billing', label: 'Assinatura', permission: 'billing.manage' },
    { href: '/audit', label: 'Auditoria', permission: 'audit.view' },
];

function logout(): void {
    router.post('/logout');
}

function stopImpersonating(): void {
    router.delete('/impersonation');
}
</script>

<template>
    <Head :title="title" />
    <div class="min-h-screen bg-gray-50">
        <div v-if="store.impersonating" class="bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white">
            Você está acessando como {{ store.user?.name }} (suporte).
            <button type="button" class="ml-2 underline" @click="stopImpersonating">Encerrar</button>
        </div>
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-3">
                    <img v-if="store.tenant?.branding.logo_url" :src="store.tenant.branding.logo_url" alt="" class="h-8 w-auto" />
                    <OrganizationSwitcher />
                </div>
                <div class="flex items-center gap-3 text-sm text-gray-600">
                    <Link href="/settings/security" class="hover:underline">{{ store.user?.name }}</Link>
                    <button type="button" class="hover:underline" @click="logout">Sair</button>
                </div>
            </div>
            <nav class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4">
                <template v-for="item in nav" :key="item.href">
                    <Link
                        v-if="item.permission === null || store.can(item.permission)"
                        :href="item.href"
                        class="border-b-2 border-transparent px-3 py-2 text-sm text-gray-600 hover:border-brand hover:text-gray-900"
                    >
                        {{ item.label }}
                    </Link>
                </template>
            </nav>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-8">
            <h1 class="mb-6 text-2xl font-semibold text-gray-900">{{ title }}</h1>
            <FlashMessage />
            <slot />
        </main>
    </div>
</template>
