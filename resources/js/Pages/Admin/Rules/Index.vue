<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
defineProps({ masteryThresholds: Array, classificationRules: Array });
</script>
<template><AdminLayout><div class="mb-5 flex justify-between"><h1 class="text-3xl font-black">Rules & Thresholds</h1><Link href="/admin/rules/history" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">History</Link></div><div class="grid gap-4 lg:grid-cols-2"><DashboardCard><h2 class="font-black">Classification Rules</h2><div v-for="rule in classificationRules" :key="rule.name" class="border-b py-3"><strong>{{ rule.name }}</strong><p class="text-sm text-muted">{{ rule.rule }}</p></div></DashboardCard><DashboardCard><h2 class="font-black">Module Mastery Thresholds</h2><div v-for="rule in masteryThresholds" :key="rule.id" class="border-b py-3"><Link class="font-black text-primary" :href="`/admin/rules/${rule.id}`">{{ rule.module?.title }}: {{ rule.min_score }}-{{ rule.max_score ?? '100' }}</Link><p>{{ rule.decision }} - {{ rule.rule_key }}</p></div></DashboardCard></div></AdminLayout></template>
