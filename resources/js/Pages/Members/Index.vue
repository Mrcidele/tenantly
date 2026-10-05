<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormError from '@/Components/FormError.vue';
import { date } from '@/lib/http';
import { useTenantStore } from '@/stores/tenant';

interface Member {
    id: string;
    role: string;
    user: { id: string; name: string; email: string };
}

defineProps<{
    members: Member[];
    invitations: { id: string; email: string; role: string; expires_at: string }[];
    roles: { value: string; label: string }[];
}>();

const store = useTenantStore();
const invite = useForm({ email: '', role: 'member' });

function changeRole(member: Member, role: string): void {
    router.patch(`/members/${member.id}`, { role }, { preserveScroll: true });
}

function remove(member: Member): void {
    if (confirm(`Remover ${member.user.name}?`)) {
        router.delete(`/members/${member.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout title="Membros">
        <form v-if="store.can('members.manage')" class="card mb-6 flex flex-wrap items-end gap-3" @submit.prevent="invite.post('/invitations', { onSuccess: () => invite.reset() })">
            <div class="min-w-64 flex-1">
                <label class="label" for="email">Convidar por e-mail</label>
                <input id="email" v-model="invite.email" type="email" class="input" required />
                <FormError :message="invite.errors.email ?? invite.errors.role" />
            </div>
            <select v-model="invite.role" class="input w-40">
                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
            </select>
            <button class="btn-primary" :disabled="invite.processing">Convidar</button>
        </form>

        <div class="card p-0">
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="member in members" :key="member.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-2">{{ member.user.name }}<br /><span class="text-gray-500">{{ member.user.email }}</span></td>
                        <td class="px-4 py-2">
                            <select
                                v-if="store.can('members.manage')"
                                :value="member.role"
                                class="input w-40"
                                @change="changeRole(member, ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                            </select>
                            <span v-else>{{ roles.find((r) => r.value === member.role)?.label }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="store.can('members.manage') || member.user.id === store.user?.id" class="text-sm text-red-600 hover:underline" @click="remove(member)">
                                {{ member.user.id === store.user?.id ? 'Sair' : 'Remover' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="invitations.length" class="card mt-6">
            <h2 class="mb-2 font-medium">Convites pendentes</h2>
            <ul class="text-sm">
                <li v-for="invitation in invitations" :key="invitation.id" class="flex justify-between py-1">
                    <span>{{ invitation.email }} · {{ invitation.role }}</span>
                    <span class="text-gray-500">expira {{ date(invitation.expires_at) }}</span>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
