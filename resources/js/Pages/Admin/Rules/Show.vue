<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ rule: Object });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><h1 class="text-3xl font-black">{{ rule.rule_key }}</h1><Link :href="`/admin/rules/${rule.id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div><DashboardCard><AdminJsonViewer :value="rule" /></DashboardCard></AdminLayout></template>
