import { defineStore } from 'pinia';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/** Contexto do tenant/usuário vindo das props compartilhadas do Inertia. */
export const useTenantStore = defineStore('tenant', () => {
    const page = usePage<SharedProps>();

    const tenant = computed(() => page.props.tenant);
    const user = computed(() => page.props.auth.user);
    const role = computed(() => page.props.auth.role);
    const impersonating = computed(() => page.props.auth.impersonating);

    function can(permission: string): boolean {
        return page.props.auth.permissions.includes(permission);
    }

    return { tenant, user, role, impersonating, can };
});
