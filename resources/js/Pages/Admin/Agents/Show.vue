<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ agent: Object, prompts: Array });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><div><h1 class="text-3xl font-black">{{ agent.name }}</h1><p class="text-muted">{{ agent.purpose }}</p></div><Link :href="`/admin/agents/${agent.id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div><div class="grid gap-4 lg:grid-cols-2"><DashboardCard><AdminJsonViewer :value="agent" /></DashboardCard><DashboardCard><h2 class="font-black">Prompt Templates</h2><div v-for="prompt in prompts" :key="prompt.id" class="border-b py-2">{{ prompt.key }} v{{ prompt.version }} - {{ prompt.status }}</div></DashboardCard></div></AdminLayout></template>
