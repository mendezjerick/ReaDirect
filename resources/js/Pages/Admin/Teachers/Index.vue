<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ teachers: Object, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/teachers', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Teachers</h1>
                <p class="text-sm text-muted">{{ teachers.total }} records found</p>
            </div>
            <Link href="/admin/teachers/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link>
        </div>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search teachers">
                <select name="school_id" :value="filters.school_id" class="rounded-xl border p-2">
                    <option value="">All schools</option>
                    <option v-for="school in filterOptions.schools" :key="school.value" :value="school.value">{{ school.label }}</option>
                </select>
                <select name="role" :value="filters.role" class="rounded-xl border p-2">
                    <option v-for="role in filterOptions.roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                </select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2">
                    <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button>
                    <Link href="/admin/teachers" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset filters</Link>
                </div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="teachers.data.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <table v-else class="w-full text-left text-sm">
                <thead><tr class="bg-primary-light text-primary"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Classes</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead>
                <tbody><tr v-for="teacher in teachers.data" :key="teacher.id" class="border-t"><td class="p-3 font-bold">{{ teacher.name }}</td><td class="p-3">{{ teacher.email }}</td><td class="p-3">{{ teacher.teaching_classes_count }}</td><td class="p-3"><StatusBadge :status="teacher.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/teachers/${teacher.id}`">View</Link></td></tr></tbody>
            </table>
        </DashboardCard>
    </AdminLayout>
</template>
