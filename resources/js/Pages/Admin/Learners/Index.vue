<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ learners: Object, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/learners', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Learners</h1>
                <p class="text-sm text-muted">{{ learners.total }} records found</p>
            </div>
            <Link href="/admin/learners/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link>
        </div>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search learners">
                <select name="school_id" :value="filters.school_id" class="rounded-xl border p-2"><option value="">All schools</option><option v-for="school in filterOptions.schools" :key="school.value" :value="school.value">{{ school.label }}</option></select>
                <select name="class_id" :value="filters.class_id" class="rounded-xl border p-2"><option value="">All classes</option><option v-for="klass in filterOptions.classes" :key="klass.value" :value="klass.value">{{ klass.label }}</option></select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2"><option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                <select name="current_module" :value="filters.current_module" class="rounded-xl border p-2"><option value="">All modules</option><option v-for="module in filterOptions.modules" :key="module.value" :value="module.value">{{ module.label }}</option></select>
                <select name="crla_level" :value="filters.crla_level" class="rounded-xl border p-2"><option value="">All CRLA levels</option><option v-for="level in filterOptions.crlaLevels" :key="level" :value="level">{{ level }}</option></select>
                <select name="reading_classification" :value="filters.reading_classification" class="rounded-xl border p-2"><option value="">All reading classifications</option><option v-for="classification in filterOptions.readingClassifications" :key="classification" :value="classification">{{ classification }}</option></select>
                <select name="diagnostic_status" :value="filters.diagnostic_status" class="rounded-xl border p-2"><option value="">All diagnostic statuses</option><option v-for="status in filterOptions.diagnosticStatuses" :key="status" :value="status">{{ status }}</option></select>
                <select name="final_status" :value="filters.final_status" class="rounded-xl border p-2"><option value="">All final statuses</option><option v-for="status in filterOptions.finalStatuses" :key="status" :value="status">{{ status }}</option></select>
                <div class="flex gap-2 md:col-span-5">
                    <button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button>
                    <Link href="/admin/learners" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset filters</Link>
                </div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="learners.data.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <table v-else class="w-full text-left text-sm">
                <thead><tr class="bg-primary-light text-primary"><th class="p-3">Learner</th><th class="p-3">School/Class</th><th class="p-3">Stage</th><th class="p-3">Module</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead>
                <tbody><tr v-for="learner in learners.data" :key="learner.public_id" class="border-t"><td class="p-3 font-bold">{{ learner.learner_code }}<br><span class="font-normal">{{ learner.first_name }} {{ learner.last_name }}</span></td><td class="p-3">{{ learner.school?.name }} / {{ learner.school_class?.name ?? '-' }}</td><td class="p-3">{{ learner.current_stage ?? '-' }}</td><td class="p-3">{{ learner.current_module?.title ?? '-' }}</td><td class="p-3"><StatusBadge :status="learner.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/learners/${learner.public_id}`">View</Link></td></tr></tbody>
            </table>
        </DashboardCard>
    </AdminLayout>
</template>
