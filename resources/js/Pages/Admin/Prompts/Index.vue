<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
defineProps({ prompts: Object });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><h1 class="text-3xl font-black">Prompt Templates</h1><div class="flex gap-2"><Link href="/admin/prompts/history" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">History</Link><Link href="/admin/prompts/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link></div></div><DashboardCard><table class="w-full text-left text-sm"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Key</th><th class="p-3">Agent</th><th class="p-3">Version</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="prompt in prompts.data" :key="prompt.id" class="border-t"><td class="p-3 font-bold">{{ prompt.key }}</td><td class="p-3">{{ prompt.agent_profile?.name }}</td><td class="p-3">{{ prompt.version }}</td><td class="p-3">{{ prompt.status }}</td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/prompts/${prompt.id}`">View</Link></td></tr></tbody></table></DashboardCard></AdminLayout></template>
