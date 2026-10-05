<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';
import { useTenantStore } from '@/stores/tenant';
import type { Project } from '@/types';

defineProps<{ projects: Project[] }>();

const store = useTenantStore();
const form = useForm({ name: '', description: '' });
</script>

<template>
    <AppLayout title="Projetos">
        <form v-if="store.can('projects.manage')" class="card mb-6 flex flex-wrap items-end gap-3" @submit.prevent="form.post('/projects', { onSuccess: () => form.reset() })">
            <div class="min-w-64 flex-1">
                <label class="label" for="name">Novo projeto</label>
                <input id="name" v-model="form.name" class="input" placeholder="Nome" required />
                <FormError :message="form.errors.name" />
            </div>
            <button class="btn-primary" :disabled="form.processing">Criar</button>
        </form>
        <div class="card p-0">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 text-left text-gray-500">
                    <tr><th class="px-4 py-2">Nome</th><th class="px-4 py-2">Tarefas</th></tr>
                </thead>
                <tbody>
                    <tr v-for="project in projects" :key="project.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-2"><Link :href="`/projects/${project.id}`" class="text-brand hover:underline">{{ project.name }}</Link></td>
                        <td class="px-4 py-2">{{ project.tasks_count ?? 0 }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
