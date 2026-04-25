<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
defineProps({ teachers: Object, filters: Object });
const filter = (e) => router.get('/admin/teachers', Object.fromEntries(new FormData(e.target).entries()), { preserveState: true });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><h1 class="text-3xl font-black">Teachers</h1><Link href="/admin/teachers/create" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Create</Link></div><DashboardCard><form class="flex gap-2" @submit.prevent="filter"><input name="search" :value="filters.search" class="w-full rounded-xl border p-2" placeholder="Search teachers"><button class="rounded-xl bg-primary px-4 font-black text-white">Filter</button></form></DashboardCard><DashboardCard class="mt-4"><table class="w-full text-left text-sm"><thead><tr class="bg-primary-light text-primary"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Classes</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody><tr v-for="teacher in teachers.data" :key="teacher.id" class="border-t"><td class="p-3 font-bold">{{ teacher.name }}</td><td class="p-3">{{ teacher.email }}</td><td class="p-3">{{ teacher.teaching_classes_count }}</td><td class="p-3"><StatusBadge :status="teacher.is_active ? 'Active' : 'Inactive'" /></td><td class="p-3"><Link class="font-black text-primary" :href="`/admin/teachers/${teacher.id}`">View</Link></td></tr></tbody></table></DashboardCard></AdminLayout></template>
