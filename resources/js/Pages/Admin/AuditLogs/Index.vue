<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
defineProps({ logs: Object, filters: Object });
const filter = (e) => router.get('/admin/audit-logs', Object.fromEntries(new FormData(e.target).entries()), { preserveState: true });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><h1 class="text-3xl font-black">Audit Logs</h1><a href="/admin/audit-logs/export" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Export CSV</a></div><DashboardCard><form class="flex gap-2" @submit.prevent="filter"><input name="action" :value="filters.action" class="w-full rounded-xl border p-2" placeholder="Filter action"><button class="rounded-xl bg-primary px-4 font-black text-white">Filter</button></form></DashboardCard><DashboardCard class="mt-4"><table class="w-full text-left text-xs"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Date</th><th class="p-3">User</th><th class="p-3">Action</th><th class="p-3">Entity</th><th class="p-3">IP</th></tr></thead><tbody><tr v-for="log in logs.data" :key="log.id" class="border-t"><td class="p-3">{{ log.created_at }}</td><td class="p-3">{{ log.user?.email ?? '-' }}</td><td class="p-3 font-bold">{{ log.action }}</td><td class="p-3">{{ log.auditable_type }} #{{ log.auditable_id }}</td><td class="p-3">{{ log.ip_address }}</td></tr></tbody></table></DashboardCard></AdminLayout></template>
