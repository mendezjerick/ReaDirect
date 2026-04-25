<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
defineProps({ school: Object, classes: Array, learners: Array });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><div><h1 class="text-3xl font-black">{{ school.name }}</h1><p class="text-muted">{{ school.district }} {{ school.division }}</p></div><Link :href="`/admin/schools/${school.id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div><div class="grid gap-4 lg:grid-cols-2"><DashboardCard><h2 class="font-black">Classes</h2><div v-for="item in classes" :key="item.id" class="border-b py-2">{{ item.name }} - {{ item.learners_count }} learners</div></DashboardCard><DashboardCard><h2 class="font-black">Recent Learners</h2><div v-for="learner in learners" :key="learner.id" class="border-b py-2">{{ learner.learner_code }} - {{ learner.first_name }} {{ learner.last_name }}</div></DashboardCard></div></AdminLayout></template>
