<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

defineProps({ logs: Object, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/audit-logs', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Audit Logs</h1>
                <p class="text-sm text-muted">{{ logs.total }} records found</p>
            </div>
            <a :href="`/admin/audit-logs/export?${new URLSearchParams(filters).toString()}`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Export CSV</a>
        </div>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-6" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2" placeholder="Search logs">
                <select name="action" :value="filters.action" class="rounded-xl border p-2"><option value="">All actions</option><option v-for="action in filterOptions.actions" :key="action.value" :value="action.value">{{ action.label }}</option></select>
                <select name="entity_type" :value="filters.entity_type" class="rounded-xl border p-2"><option value="">All entities</option><option v-for="type in filterOptions.entityTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
                <select name="role" :value="filters.role" class="rounded-xl border p-2"><option v-for="role in filterOptions.roles" :key="role.value" :value="role.value">{{ role.label }}</option></select>
                <input name="date_from" :value="filters.date_from" type="date" class="rounded-xl border p-2">
                <input name="date_to" :value="filters.date_to" type="date" class="rounded-xl border p-2">
                <div class="flex gap-2 md:col-span-6"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button><a href="/admin/audit-logs" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset</a></div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="logs.data.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <div v-else class="overflow-x-auto rounded-xl border border-border/60">
                <table class="w-full text-left text-xs"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Date</th><th class="p-3">User</th><th class="p-3">Action</th><th class="p-3">Entity</th><th class="p-3">IP</th></tr></thead><tbody><tr v-for="log in logs.data" :key="log.id" class="border-t border-border/60"><td class="p-3 whitespace-nowrap">{{ log.created_at }}</td><td class="p-3">{{ log.user?.email ?? '-' }}</td><td class="p-3 font-bold">{{ log.action }}</td><td class="p-3 whitespace-nowrap">{{ log.auditable_type }} #{{ log.auditable_id }}</td><td class="p-3">{{ log.ip_address }}</td></tr></tbody></table>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>
