<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';

defineProps<{ title: string }>();
const page = usePage<{ admin: { name: string } | null }>();
</script>

<template>
    <Head :title="`Admin · ${title}`" />
    <div class="min-h-screen bg-slate-100">
        <header class="bg-slate-900 text-slate-100">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <nav class="flex gap-4 text-sm">
                    <span class="font-semibold">Tenantly Admin</span>
                    <Link href="/" class="hover:underline">Métricas</Link>
                    <Link href="/tenants" class="hover:underline">Tenants</Link>
                    <Link href="/plans" class="hover:underline">Planos</Link>
                    <Link href="/invoices" class="hover:underline">Faturas</Link>
                </nav>
                <div class="text-sm">
                    {{ page.props.admin?.name }}
                    <button class="ml-3 underline" @click="router.post('/logout')">Sair</button>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-4 py-8">
            <h1 class="mb-6 text-2xl font-semibold">{{ title }}</h1>
            <FlashMessage />
            <slot />
        </main>
    </div>
</template>
