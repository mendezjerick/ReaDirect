<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ schools: Object, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/schools', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Schools</h1>
                <p class="text-sm text-muted">{{ schools.total }} records found</p>
            </div>
            <Link href="/admin/schools/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link>
        </div>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border border-border px-4 py-2 md:col-span-2" placeholder="Search schools">
                <select name="status" :value="filters.status" class="rounded-xl border p-2">
                    <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <select name="district" :value="filters.district" class="rounded-xl border p-2">
                    <option value="">All districts</option>
                    <option v-for="district in filterOptions.districts" :key="district.value" :value="district.value">{{ district.label }}</option>
                </select>
                <select name="division" :value="filters.division" class="rounded-xl border p-2">
                    <option value="">All divisions</option>
                    <option v-for="division in filterOptions.divisions" :key="division.value" :value="division.value">{{ division.label }}</option>
                </select>
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button>
                    <Link href="/admin/schools" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset filters</Link>
                </div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="schools.data.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <table v-else class="w-full text-left text-sm">
                <thead><tr class="bg-primary-light text-primary"><th class="p-3">School</th><th class="p-3">District</th><th class="p-3">Classes</th><th class="p-3">Learners</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead>
                <tbody><tr v-for="school in schools.data" :key="school.id" class="border-t"><td class="p-3 font-bold">{{ school.name }}</td><td class="p-3">{{ school.district ?? '-' }}</td><td class="p-3">{{ school.classes_count }}</td><td class="p-3">{{ school.learners_count }}</td><td class="p-3"><StatusBadge :status="school.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/schools/${school.id}`">View</Link></td></tr></tbody>
            </table>
        </DashboardCard>
    </AdminLayout>
</template>
