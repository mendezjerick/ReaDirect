<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { ArrowLeft, Pencil, Target } from 'lucide-vue-next';

defineProps({ rule: Object });

const detailRows = (rule) => [
    { label: 'Rule Key', value: rule.rule_key },
    { label: 'Decision', value: rule.decision },
    { label: 'Min Score', value: rule.min_score },
    { label: 'Max Score', value: rule.max_score ?? '100' },
    { label: 'Module', value: rule.module?.title ?? '—' },
    { label: 'Next Module Key', value: rule.next_module_key ?? '—' },
    { label: 'Created', value: rule.created_at ?? '—' },
    { label: 'Updated', value: rule.updated_at ?? '—' },
];

const scoreColor = (min, max) => {
    const mid = ((min ?? 0) + (max ?? 100)) / 2;
    if (mid >= 80) return 'bg-green-50 text-green-600';
    if (mid >= 50) return 'bg-amber-50 text-amber-600';
    return 'bg-red-50 text-red-600';
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">{{ rule.rule_key }}</h1>
                <div class="mt-1.5 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold" :class="scoreColor(rule.min_score, rule.max_score)">
                        {{ rule.min_score }}-{{ rule.max_score ?? '100' }}
                    </span>
                    <StatusBadge :status="rule.decision" />
                </div>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/rules" class="group inline-flex items-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                    <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                    Back
                </Link>
                <Link :href="`/admin/rules/${rule.id}/edit`" class="group inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                    <Pencil class="size-4" />
                    Edit
                </Link>
            </div>
        </div>

        <DashboardCard class="rs-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                    <Target class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Rule Details</h2>
            </div>

            <div class="space-y-1">
                <div v-for="row in detailRows(rule)" :key="row.label" class="flex items-center justify-between gap-3 rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors duration-150 hover:bg-slate-100">
                    <span class="font-semibold text-muted">{{ row.label }}</span>
                    <span class="font-bold text-text text-right truncate max-w-[60%]">{{ row.value }}</span>
                </div>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.rs-card-in { animation: rs-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes rs-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
