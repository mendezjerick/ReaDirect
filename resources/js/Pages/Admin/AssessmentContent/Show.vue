<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ item: Object, usageCount: Number });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><div><h1 class="text-3xl font-black">{{ item.title }}</h1><p class="text-muted">{{ item.content_type }} - used {{ usageCount }} times</p></div><Link :href="`/admin/assessment-content/${item.id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div><DashboardCard><AdminJsonViewer :value="item" /></DashboardCard></AdminLayout></template>
