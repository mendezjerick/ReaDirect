<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
const props = defineProps({ rule: Object });
const form = useForm({ min_score: props.rule.min_score, max_score: props.rule.max_score, decision: props.rule.decision, next_module_key: props.rule.next_module_key, rule_key: props.rule.rule_key });
</script>
<template><AdminLayout><h1 class="mb-5 text-3xl font-black">Edit Rule</h1><DashboardCard><form class="grid gap-4" @submit.prevent="form.put(`/admin/rules/${rule.id}`)"><input v-model="form.min_score" class="rounded-xl border p-3" type="number" step="0.01"><input v-model="form.max_score" class="rounded-xl border p-3" type="number" step="0.01"><input v-model="form.decision" class="rounded-xl border p-3"><input v-model="form.next_module_key" class="rounded-xl border p-3"><input v-model="form.rule_key" class="rounded-xl border p-3"><button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Save</button></form></DashboardCard></AdminLayout></template>
