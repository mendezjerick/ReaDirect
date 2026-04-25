<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ activities: Object, filters: Object, modules: Array, filterOptions: Object });

const filter = (event) => router.get('/admin/module-content', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Module Content</h1>
                <p class="text-sm text-muted">{{ activities.total }} records found</p>
            </div>
            <Link href="/admin/module-content/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link>
        </div>

        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-6" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search title, prompt, type">
                <select name="module" :value="filters.module" class="rounded-xl border p-2">
                    <option value="">All modules</option>
                    <option v-for="module in filterOptions.modules" :key="module.value" :value="module.value">{{ module.label }}</option>
                </select>
                <select name="activity_type" :value="filters.activity_type" class="rounded-xl border p-2">
                    <option value="">All activity types</option>
                    <option v-for="type in filterOptions.activityTypes" :key="`${type.module}-${type.value}`" :value="type.value">{{ type.label }}</option>
                </select>
                <select name="is_mastery_item" :value="filters.is_mastery_item" class="rounded-xl border p-2">
                    <option v-for="option in filterOptions.mastery" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2">
                    <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                </select>
                <div class="flex gap-2 md:col-span-6">
                    <button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button>
                    <Link href="/admin/module-content" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset filters</Link>
                </div>
            </form>
        </DashboardCard>

        <DashboardCard class="mt-4">
            <div v-if="activities.data.length === 0" class="py-10 text-center font-bold text-muted">
                No records found for the selected filters.
            </div>
            <table v-else class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-primary-light text-primary">
                        <th class="p-3">Module</th>
                        <th class="p-3">Type</th>
                        <th class="p-3">Title</th>
                        <th class="p-3">Sequence</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="activity in activities.data" :key="activity.id" class="border-t">
                        <td class="p-3">{{ activity.module?.title }}</td>
                        <td class="p-3">{{ activity.activity_type }}</td>
                        <td class="p-3 font-bold">{{ activity.title }}</td>
                        <td class="p-3">{{ activity.sequence }}</td>
                        <td class="p-3"><StatusBadge :status="(activity.configuration?.is_active ?? activity.learning_content?.is_active ?? true) ? 'Active' : 'Inactive'" /></td>
                        <td class="p-3"><Link class="font-black text-primary" :href="`/admin/module-content/${activity.id}`">View</Link></td>
                    </tr>
                </tbody>
            </table>
        </DashboardCard>
    </AdminLayout>
</template>
