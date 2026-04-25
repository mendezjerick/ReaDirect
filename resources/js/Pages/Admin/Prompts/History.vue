<script setup>
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ prompts: Array });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Prompt History</h1><DashboardCard><AdminJsonViewer :value="prompts" /></DashboardCard></AdminLayout></template>
