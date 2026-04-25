<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ teacher: Object, classes: Array });
const assigned = props.teacher?.teaching_classes?.map((item) => item.id) ?? [];
const form = useForm({ name: props.teacher?.name ?? '', email: props.teacher?.email ?? '', class_ids: assigned });
const submit = () => props.teacher ? form.put(`/admin/teachers/${props.teacher.id}`) : form.post('/admin/teachers');
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">{{ teacher ? 'Edit Teacher' : 'Create Teacher' }}</h1><DashboardCard><form class="grid gap-4" @submit.prevent="submit"><input v-model="form.name" class="rounded-xl border p-3" placeholder="Teacher name"><input v-model="form.email" class="rounded-xl border p-3" placeholder="Email"><label class="font-bold">Classes</label><select v-model="form.class_ids" multiple class="min-h-40 rounded-xl border p-3"><option v-for="item in classes" :key="item.id" :value="item.id">{{ item.school?.name }} - {{ item.name }}</option></select><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
