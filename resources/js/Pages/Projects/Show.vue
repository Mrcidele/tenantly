<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTenantStore } from '@/stores/tenant';
import type { Project, Task } from '@/types';

const props = defineProps<{ project: Project; tasks: Task[] }>();
const store = useTenantStore();

const labels: Record<Task['status'], string> = { todo: 'A fazer', doing: 'Em andamento', done: 'Concluída' };

function destroy(): void {
    if (confirm('Excluir o projeto e suas tarefas?')) {
        router.delete(`/projects/${props.project.id}`);
    }
}
</script>

<template>
    <AppLayout :title="project.name">
        <p class="mb-6 text-gray-600">{{ project.description }}</p>
        <div class="card p-0">
            <ul class="divide-y divide-gray-100">
                <li v-for="task in tasks" :key="task.id" class="flex justify-between px-4 py-2 text-sm">
                    <span>{{ task.title }}</span>
                    <span class="text-gray-500">{{ labels[task.status] }}</span>
                </li>
                <li v-if="tasks.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">Nenhuma tarefa.</li>
            </ul>
        </div>
        <button v-if="store.can('projects.manage')" class="btn-danger mt-6" @click="destroy">Excluir projeto</button>
    </AppLayout>
</template>
