<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

defineProps({ learnersCount: Number, sandboxAssessments: Array, sandboxModules: Array, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/testing', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5"><h1 class="text-3xl font-black">Testing / QA Mode</h1><p class="text-muted">Admin-only sandbox attempts, page jumps, STT debug, and LLM debug.</p></div>
        <div class="grid gap-4 md:grid-cols-3"><DashboardCard><h2 class="font-black">Learners</h2><p class="text-3xl font-black text-primary">{{ learnersCount }}</p><Link href="/admin/testing/learners" class="mt-3 inline-block font-black text-primary">Select learner</Link></DashboardCard><DashboardCard><h2 class="font-black">Assessment attempts</h2><p class="text-3xl font-black text-primary">{{ sandboxAssessments.length }}</p></DashboardCard><DashboardCard><h2 class="font-black">Module attempts</h2><p class="text-3xl font-black text-primary">{{ sandboxModules.length }}</p></DashboardCard></div>
        <DashboardCard class="mt-5">
            <form class="grid gap-2 md:grid-cols-6" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2" placeholder="Search learner">
                <select name="attempt_type" :value="filters.attempt_type" class="rounded-xl border p-2"><option v-for="type in filterOptions.attemptTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
                <select name="sandbox" :value="filters.sandbox" class="rounded-xl border p-2"><option v-for="option in filterOptions.sandbox" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <select name="module" :value="filters.module" class="rounded-xl border p-2"><option value="">All modules</option><option v-for="module in filterOptions.modules" :key="module.value" :value="module.value">{{ module.label }}</option></select>
                <input name="date_from" :value="filters.date_from" type="date" class="rounded-xl border p-2">
                <input name="date_to" :value="filters.date_to" type="date" class="rounded-xl border p-2">
                <input name="status" :value="filters.status" class="rounded-xl border p-2" placeholder="Status">
                <div class="flex gap-2 md:col-span-5"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button><Link href="/admin/testing" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset filters</Link></div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-5">
            <h2 class="font-black">Recent Attempts</h2>
            <div v-if="sandboxAssessments.length === 0 && sandboxModules.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <div v-for="item in sandboxAssessments" :key="item.public_id" class="border-b py-2"><Link class="font-black text-primary" :href="`/admin/testing/assessment/${item.public_id}/debug`">{{ item.learner?.learner_code }} - {{ item.attempt_type }} - {{ item.status }}</Link></div>
            <div v-for="item in sandboxModules" :key="item.public_id" class="border-b py-2"><Link class="font-black text-primary" :href="`/admin/testing/module/${item.id}/debug`">{{ item.learner?.learner_code }} - {{ item.module?.title }} - {{ item.status }}</Link></div>
        </DashboardCard>
    </AdminLayout>
</template>
