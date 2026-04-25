<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ system: Object });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">System Monitoring</h1><div class="grid gap-4 lg:grid-cols-2"><DashboardCard v-for="(section, key) in system" :key="key"><h2 class="mb-3 font-black capitalize">{{ key }}</h2><AdminJsonViewer :value="section" /></DashboardCard></div></AdminLayout></template>
