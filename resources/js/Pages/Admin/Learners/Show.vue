<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AdminJsonViewer from '../../../Components/Admin/AdminJsonViewer.vue';
defineProps({ learner: Object, progress: Object, testingUrl: String });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><div><h1 class="text-3xl font-black">{{ learner.learner_code }}</h1><p class="text-muted">{{ learner.first_name }} {{ learner.last_name }}</p></div><div class="flex gap-2"><Link :href="testingUrl" class="rounded-xl bg-amber-600 px-4 py-2 font-black text-white">Testing Jump</Link><Link :href="`/admin/learners/${learner.public_id}/edit`" class="rounded-xl bg-primary px-4 py-2 font-black text-white">Edit</Link></div></div><DashboardCard><AdminJsonViewer :value="progress" /></DashboardCard></AdminLayout></template>
