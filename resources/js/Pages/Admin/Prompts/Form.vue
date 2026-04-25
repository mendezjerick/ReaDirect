<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ prompt: Object, agents: Array });
const form = useForm({ agent_profile_id: props.prompt?.agent_profile_id ?? '', key: props.prompt?.key ?? '', version: props.prompt?.version ?? 1, status: props.prompt?.status ?? 'draft', template: props.prompt?.template ?? '', variables: props.prompt?.variables ? JSON.stringify(props.prompt.variables, null, 2) : '' });
const submit = () => props.prompt ? form.put(`/admin/prompts/${props.prompt.id}`) : form.post('/admin/prompts');
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">{{ prompt ? 'Edit Prompt' : 'Create Prompt' }}</h1><DashboardCard><form class="grid gap-4" @submit.prevent="submit"><select v-model="form.agent_profile_id" class="rounded-xl border p-3"><option value="">Agent</option><option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option></select><input v-model="form.key" class="rounded-xl border p-3" placeholder="Key"><input v-model="form.version" class="rounded-xl border p-3" type="number"><select v-model="form.status" class="rounded-xl border p-3"><option>draft</option><option>active</option><option>inactive</option></select><textarea v-model="form.template" class="min-h-52 rounded-xl border p-3" placeholder="Template" /><textarea v-model="form.variables" class="rounded-xl border p-3" placeholder="Variables JSON" /><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
