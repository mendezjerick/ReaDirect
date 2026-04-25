<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

defineProps({ masteryThresholds: Array, classificationRules: Array, filters: Object, filterOptions: Object });

const filter = (event) => router.get('/admin/rules', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true });
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex justify-between"><h1 class="text-3xl font-black">Rules & Thresholds</h1><Link href="/admin/rules/history" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">History</Link></div>
        <DashboardCard class="mb-4">
            <form class="grid gap-2 md:grid-cols-4" @submit.prevent="filter">
                <input name="search" :value="filters.search" class="rounded-xl border p-2 md:col-span-2" placeholder="Search rules">
                <select name="rule_type" :value="filters.rule_type" class="rounded-xl border p-2"><option v-for="type in filterOptions.ruleTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select>
                <div class="flex gap-2"><button class="rounded-xl bg-primary px-4 py-2 font-black text-white">Filter</button><Link href="/admin/rules" class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary">Reset</Link></div>
            </form>
        </DashboardCard>
        <div class="grid gap-4 lg:grid-cols-2">
            <DashboardCard>
                <h2 class="font-black">Classification Rules</h2>
                <div v-if="classificationRules.length === 0" class="py-8 text-center font-bold text-muted">No records found for the selected filters.</div>
                <div v-for="rule in classificationRules" :key="rule.name" class="border-b py-3"><strong>{{ rule.name }}</strong><p class="text-sm text-muted">{{ rule.rule }}</p></div>
            </DashboardCard>
            <DashboardCard>
                <h2 class="font-black">Module Mastery Thresholds</h2>
                <div v-if="masteryThresholds.length === 0" class="py-8 text-center font-bold text-muted">No records found for the selected filters.</div>
                <div v-for="rule in masteryThresholds" :key="rule.id" class="border-b py-3"><Link class="font-black text-primary" :href="`/admin/rules/${rule.id}`">{{ rule.module?.title }}: {{ rule.min_score }}-{{ rule.max_score ?? '100' }}</Link><p>{{ rule.decision }} - {{ rule.rule_key }}</p></div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>
