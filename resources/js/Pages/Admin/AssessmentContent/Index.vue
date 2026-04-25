<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ items: Object, filters: Object, contentTypes: Array, filterOptions: Object });

const filter = (event) => router.get('/admin/assessment-content', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Assessment Content</h1>
                <p class="text-sm text-muted">{{ items.total }} records found</p>
            </div>
            <Link href="/admin/assessment-content/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link>
        </div>

        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search content">
                <select name="content_type" :value="filters.content_type" class="rounded-xl border p-2">
                    <option value="">All content types</option>
                    <option v-for="type in contentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                </select>
                <select name="difficulty" :value="filters.difficulty" class="rounded-xl border p-2">
                    <option value="">All difficulties</option>
                    <option v-for="difficulty in filterOptions.difficulties" :key="difficulty.value" :value="difficulty.value">{{ difficulty.label }}</option>
                </select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2">
                    <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button>
                    <Link href="/admin/assessment-content" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset filters</Link>
                </div>
            </form>
        </DashboardCard>

        <DashboardCard class="mt-4">
            <div v-if="items.data.length === 0" class="py-10 text-center font-bold text-muted">
                No records found for the selected filters.
            </div>
            <table v-else class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-primary-light text-primary">
                        <th class="p-3">Type</th>
                        <th class="p-3">Difficulty</th>
                        <th class="p-3">Title</th>
                        <th class="p-3">Prompt</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items.data" :key="item.id" class="border-t">
                        <td class="p-3">{{ item.content_type }}</td>
                        <td class="p-3">{{ item.difficulty }}</td>
                        <td class="p-3 font-bold">{{ item.title }}</td>
                        <td class="p-3">{{ item.prompt }}</td>
                        <td class="p-3"><StatusBadge :status="item.is_active ? 'Active' : 'Inactive'" /></td>
                        <td class="p-3"><Link class="font-black text-primary" :href="`/admin/assessment-content/${item.id}`">View</Link></td>
                    </tr>
                </tbody>
            </table>
        </DashboardCard>
    </AdminLayout>
</template>
