<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
defineProps({ schools: Object, filters: Object });
const filter = (event) => router.get('/admin/schools', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>
<template>
    <AdminLayout>
        <div class="mb-5 flex items-center justify-between"><div><h1 class="text-3xl font-black">Schools</h1><p class="text-muted">Manage school records.</p></div><Link href="/admin/schools/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link></div>
        <DashboardCard><form class="flex gap-2" @submit.prevent="filter"><input name="search" :value="filters.search" class="w-full rounded-xl border border-border px-4 py-2" placeholder="Search schools"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button></form></DashboardCard>
        <DashboardCard class="mt-4"><table class="w-full text-left text-sm"><thead><tr class="bg-primary-light text-primary"><th class="p-3">School</th><th class="p-3">District</th><th class="p-3">Classes</th><th class="p-3">Learners</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="school in schools.data" :key="school.id" class="border-t"><td class="p-3 font-bold">{{ school.name }}</td><td class="p-3">{{ school.district ?? '-' }}</td><td class="p-3">{{ school.classes_count }}</td><td class="p-3">{{ school.learners_count }}</td><td class="p-3"><StatusBadge :status="school.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/schools/${school.id}`">View</Link></td></tr></tbody></table></DashboardCard>
    </AdminLayout>
</template>
