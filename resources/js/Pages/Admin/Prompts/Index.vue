<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

defineProps({ prompts: Object, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/prompts', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between">
            <div>
                <h1 class="text-3xl font-black">Prompt Templates</h1>
                <p class="text-sm text-muted">{{ prompts.total }} records found</p>
            </div>
            <div class="flex gap-2"><Link href="/admin/prompts/history" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">History</Link><Link href="/admin/prompts/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link></div>
        </div>
        <DashboardCard>
            <form class="grid gap-2 md:grid-cols-5" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2" placeholder="Search prompts">
                <select name="prompt_type" :value="filters.prompt_type" class="rounded-xl border p-2"><option value="">All prompt types</option><option v-for="type in filterOptions.promptTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
                <select name="agent_type" :value="filters.agent_type" class="rounded-xl border p-2"><option value="">All agents</option><option v-for="type in filterOptions.agentTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
                <select name="status" :value="filters.status" class="rounded-xl border p-2"><option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                <div class="flex gap-2"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button><Link href="/admin/prompts" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset</Link></div>
            </form>
        </DashboardCard>
        <DashboardCard class="mt-4">
            <div v-if="prompts.data.length === 0" class="py-10 text-center font-bold text-muted">No records found for the selected filters.</div>
            <table v-else class="w-full text-left text-sm"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Key</th><th class="p-3">Agent</th><th class="p-3">Version</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="prompt in prompts.data" :key="prompt.id" class="border-t"><td class="p-3 font-bold">{{ prompt.key }}</td><td class="p-3">{{ prompt.agent_profile?.name }}</td><td class="p-3">{{ prompt.version }}</td><td class="p-3">{{ prompt.status }}</td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/prompts/${prompt.id}`">View</Link></td></tr></tbody></table>
        </DashboardCard>
    </AdminLayout>
</template>
