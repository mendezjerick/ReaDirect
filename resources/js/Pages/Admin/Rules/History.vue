<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ events: Array });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Rule History</h1><DashboardCard><AdminJsonViewer :value="events" /></DashboardCard></AdminLayout></template>
