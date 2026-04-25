<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
defineProps({ learners: Object, filters: Object });
const filter = (e) => router.get('/admin/testing/learners', Object.fromEntries(new FormData(e.target).entries()), { preserveState: true });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Select Learner for Testing</h1><DashboardCard><form class="flex gap-2" @submit.prevent="filter"><input name="search" :value="filters.search" class="w-full rounded-xl border p-2" placeholder="Search learner"><button class="rounded-xl bg-primary px-4 font-black text-white">Filter</button></form></DashboardCard><DashboardCard class="mt-4"><table class="w-full text-left text-sm"><tbody><tr v-for="learner in learners.data" :key="learner.public_id" class="border-b"><td class="p-3 font-bold">{{ learner.learner_code }} - {{ learner.first_name }} {{ learner.last_name }}</td><td class="p-3">{{ learner.school?.name }} / {{ learner.school_class?.name }}</td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/testing/learner/${learner.public_id}/jump`">Use for testing</Link></td></tr></tbody></table></DashboardCard></AdminLayout></template>
