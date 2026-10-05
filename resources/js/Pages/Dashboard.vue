<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date } from '@/lib/http';
import type { Project } from '@/types';

defineProps<{
    stats: { projects: number; open_tasks: number; members: number };
    recentProjects: Pick<Project, 'id' | 'name' | 'created_at'>[];
}>();
</script>

<template>
    <AppLayout title="Início">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card"><p class="text-sm text-gray-500">Projetos</p><p class="text-3xl font-semibold">{{ stats.projects }}</p></div>
            <div class="card"><p class="text-sm text-gray-500">Tarefas abertas</p><p class="text-3xl font-semibold">{{ stats.open_tasks }}</p></div>
            <div class="card"><p class="text-sm text-gray-500">Membros</p><p class="text-3xl font-semibold">{{ stats.members }}</p></div>
        </div>
        <div class="card mt-6">
            <h2 class="mb-3 font-medium">Projetos recentes</h2>
            <ul class="divide-y divide-gray-100">
                <li v-for="project in recentProjects" :key="project.id" class="flex justify-between py-2 text-sm">
                    <Link :href="`/projects/${project.id}`" class="text-brand hover:underline">{{ project.name }}</Link>
                    <span class="text-gray-400">{{ date(project.created_at) }}</span>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
