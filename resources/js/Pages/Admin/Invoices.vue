<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { date, money } from '@/lib/http';

defineProps<{ invoices: { id: string; tenant_id: string; amount_cents: number; status: string; description: string; due_at: string | null; paid_at: string | null }[] }>();
</script>

<template>
    <AdminLayout title="Faturas">
        <div class="card p-0">
            <table class="w-full text-sm">
                <tr v-for="invoice in invoices" :key="invoice.id" class="border-b border-gray-100">
                    <td class="px-4 py-2 font-mono text-xs">{{ invoice.tenant_id }}</td>
                    <td class="px-4 py-2">{{ invoice.description }}</td>
                    <td class="px-4 py-2">{{ money(invoice.amount_cents) }}</td>
                    <td class="px-4 py-2">{{ invoice.status }}</td>
                    <td class="px-4 py-2">{{ date(invoice.due_at) }}</td>
                </tr>
            </table>
        </div>
    </AdminLayout>
</template>
