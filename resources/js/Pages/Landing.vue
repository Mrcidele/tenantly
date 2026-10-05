<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="SaaS multi-tenant" />
    <div class="min-h-screen bg-gradient-to-b from-indigo-50 to-white">
        <div class="mx-auto max-w-3xl px-6 py-24 text-center">
            <h1 class="text-4xl font-bold text-gray-900">Tenantly</h1>
            <p class="mt-4 text-lg text-gray-600">Projetos e equipes com isolamento de dados garantido pela infraestrutura.</p>
            <Link href="/signup" class="btn-primary mt-8">Criar minha organização</Link>
        </div>
    </div>
</template>
