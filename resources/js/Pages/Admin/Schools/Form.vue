<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ school: Object });
const form = useForm({ name: props.school?.name ?? '', district: props.school?.district ?? '', division: props.school?.division ?? '' });
const submit = () => props.school ? form.put(`/admin/schools/${props.school.id}`) : form.post('/admin/schools');
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">{{ school ? 'Edit School' : 'Create School' }}</h1><DashboardCard><form class="grid gap-4" @submit.prevent="submit"><input v-model="form.name" class="rounded-xl border p-3" placeholder="School name"><input v-model="form.district" class="rounded-xl border p-3" placeholder="District"><input v-model="form.division" class="rounded-xl border p-3" placeholder="Division"><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
