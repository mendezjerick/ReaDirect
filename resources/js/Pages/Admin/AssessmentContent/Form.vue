<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ item: Object });
const form = useForm({ content_type: props.item?.content_type ?? '', title: props.item?.title ?? '', prompt: props.item?.prompt ?? '', difficulty: props.item?.difficulty ?? 'grade_1', accepted_answers: props.item?.accepted_answers ? JSON.stringify(props.item.accepted_answers, null, 2) : '', payload: props.item?.payload ? JSON.stringify(props.item.payload, null, 2) : '', is_active: props.item?.is_active ?? true });
const submit = () => props.item ? form.put(`/admin/assessment-content/${props.item.id}`) : form.post('/admin/assessment-content');
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">{{ item ? 'Edit Assessment Content' : 'Create Assessment Content' }}</h1><DashboardCard><form class="grid gap-4" @submit.prevent="submit"><input v-model="form.content_type" class="rounded-xl border p-3" placeholder="Content type"><input v-model="form.title" class="rounded-xl border p-3" placeholder="Title"><textarea v-model="form.prompt" class="rounded-xl border p-3" placeholder="Prompt" /><input v-model="form.difficulty" class="rounded-xl border p-3" placeholder="Difficulty"><textarea v-model="form.accepted_answers" class="rounded-xl border p-3" placeholder="Accepted answers JSON or pipe-separated" /><textarea v-model="form.payload" class="rounded-xl border p-3" placeholder="Payload JSON" /><label class="flex gap-2"><input v-model="form.is_active" type="checkbox"> Active</label><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
