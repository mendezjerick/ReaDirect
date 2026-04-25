<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

defineProps({ agents: Array, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/agents', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <h1 class="mb-5 text-3xl font-black">Agents</h1>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search agents">
                <select name="agent_type" :value="filters.agent_type" class="rounded-xl border p-2"><option value="">All agent types</option><option v-for="type in filterOptions.agentTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2"><option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                <div class="flex gap-2"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button><Link href="/admin/agents" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset</Link></div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="agents.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <table v-else class="w-full text-left text-sm"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Agent</th><th class="p-3">Type</th><th class="p-3">LLM</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="agent in agents" :key="agent.id" class="border-t"><td class="p-3 font-bold">{{ agent.name }}</td><td class="p-3">{{ agent.agent_type }}</td><td class="p-3">{{ agent.uses_llm ? 'Yes' : 'No' }}</td><td class="p-3"><StatusBadge :status="agent.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/agents/${agent.id}`">View</Link></td></tr></tbody></table>
        </DashboardCard>
    </AdminLayout>
</template>
