<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ activity: Object, usageCount: Number });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><div><h1 class="text-3xl font-black">{{ activity.title }}</h1><p class="text-muted">{{ activity.module?.title }} - used {{ usageCount }} times</p></div><Link :href="`/admin/module-content/${activity.id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div><DashboardCard><AdminJsonViewer :value="activity" /></DashboardCard></AdminLayout></template>
