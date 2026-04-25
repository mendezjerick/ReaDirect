<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
defineProps({ teacher: Object, classes: Array });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><div><h1 class="text-3xl font-black">{{ teacher.name }}</h1><p class="text-muted">{{ teacher.email }}</p></div><Link :href="`/admin/teachers/${teacher.id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div><DashboardCard><h2 class="font-black">Assigned Classes</h2><div v-for="item in classes" :key="item.id" class="border-b py-2">{{ item.school?.name }} - {{ item.name }} - {{ item.learners_count }} learners</div></DashboardCard></AdminLayout></template>
