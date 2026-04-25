<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
defineProps({ agents: Array });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Agents</h1><DashboardCard><table class="w-full text-left text-sm"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Agent</th><th class="p-3">Type</th><th class="p-3">LLM</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="agent in agents" :key="agent.id" class="border-t"><td class="p-3 font-bold">{{ agent.name }}</td><td class="p-3">{{ agent.agent_type }}</td><td class="p-3">{{ agent.uses_llm ? 'Yes' : 'No' }}</td><td class="p-3"><StatusBadge :status="agent.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/agents/${agent.id}`">View</Link></td></tr></tbody></table></DashboardCard></AdminLayout></template>
