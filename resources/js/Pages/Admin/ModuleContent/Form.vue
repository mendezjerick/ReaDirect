<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ activity: Object, modules: Array });
const form = useForm({ module_id: props.activity?.module_id ?? '', sequence: props.activity?.sequence ?? 1, activity_type: props.activity?.activity_type ?? '', title: props.activity?.title ?? '', configuration: props.activity?.configuration ? JSON.stringify(props.activity.configuration, null, 2) : '', is_active: props.activity?.configuration?.is_active ?? true });
const submit = () => props.activity ? form.put(`/admin/module-content/${props.activity.id}`) : form.post('/admin/module-content');
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">{{ activity ? 'Edit Module Activity' : 'Create Module Activity' }}</h1><DashboardCard><form class="grid gap-4" @submit.prevent="submit"><select v-model="form.module_id" class="rounded-xl border p-3"><option value="">Module</option><option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option></select><input v-model="form.sequence" class="rounded-xl border p-3" type="number" placeholder="Sequence"><input v-model="form.activity_type" class="rounded-xl border p-3" placeholder="Activity type"><input v-model="form.title" class="rounded-xl border p-3" placeholder="Title"><textarea v-model="form.configuration" class="rounded-xl border p-3" placeholder="Configuration JSON" /><label class="flex gap-2"><input v-model="form.is_active" type="checkbox"> Active</label><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
