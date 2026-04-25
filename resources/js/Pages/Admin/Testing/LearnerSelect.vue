<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

defineProps({ learners: Object, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/testing/learners', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <h1 class="mb-5 text-3xl font-black">Select Learner for Testing</h1>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search learner">
                <select name="school_id" :value="filters.school_id" class="rounded-xl border p-2"><option value="">All schools</option><option v-for="school in filterOptions.schools" :key="school.value" :value="school.value">{{ school.label }}</option></select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2"><option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                <div class="flex gap-2"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button><Link href="/admin/testing/learners" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset</Link></div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="learners.data.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <table v-else class="w-full text-left text-sm"><tbody><tr v-for="learner in learners.data" :key="learner.public_id" class="border-b"><td class="p-3 font-bold">{{ learner.learner_code }} - {{ learner.first_name }} {{ learner.last_name }}</td><td class="p-3">{{ learner.school?.name }} / {{ learner.school_class?.name }}</td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/testing/learner/${learner.public_id}/jump`">Use for testing</Link></td></tr></tbody></table>
        </DashboardCard>
    </AdminLayout>
</template>
