<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ agent: Object });
const form = useForm({ name: props.agent.name, purpose: props.agent.purpose, sprite_path: props.agent.sprite_path ?? '', default_state: props.agent.default_state ?? 'idle', voice_settings: props.agent.voice_settings ? JSON.stringify(props.agent.voice_settings, null, 2) : '', is_active: props.agent.is_active ?? true });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Edit Agent</h1><DashboardCard><form class="grid gap-4" @submit.prevent="form.put(`/admin/agents/${agent.id}`)"><input v-model="form.name" class="rounded-xl border p-3"><textarea v-model="form.purpose" class="rounded-xl border p-3" /><input v-model="form.sprite_path" class="rounded-xl border p-3" placeholder="Sprite path"><input v-model="form.default_state" class="rounded-xl border p-3"><textarea v-model="form.voice_settings" class="rounded-xl border p-3" placeholder="Voice settings JSON" /><label class="flex gap-2"><input v-model="form.is_active" type="checkbox"> Active</label><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
