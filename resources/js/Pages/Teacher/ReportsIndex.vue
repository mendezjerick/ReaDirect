<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { FileText, Download, FileDown, ArrowRight } from 'lucide-vue-next';

defineProps({ reports: Array, learners: Array });
</script>

<template>
    <TeacherLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Reports</h1>
                <p class="mt-1 text-sm font-medium text-muted">CSV exports and PDF placeholders</p>
            </div>
            <a href="/teacher/reports/class-summary" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-orange-600 hover:shadow-md hover:shadow-orange-500/20 active:scale-[0.97]">
                <Download class="size-4" />
                Export Class CSV
            </a>
        </div>

        <!-- ── Report types cards ──────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <DashboardCard v-for="(report, index) in reports" :key="report.type" class="rp-card-in flex flex-col justify-between" :style="{ '--card-delay': `${index * 80}ms` }">
                <div>
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-orange-500">
                        <FileText class="size-5" />
                    </div>
                    <h2 class="text-sm font-bold text-text">{{ report.title }}</h2>
                    <p class="mt-1.5 text-[12px] font-medium leading-relaxed text-muted">{{ report.description }}</p>
                </div>
            </DashboardCard>
        </div>

        <!-- ── Learner reports table ───────────────────────── -->
        <DashboardCard class="rp-card-in" style="--card-delay: 200ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <FileDown class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Learner Reports</h2>
                </div>
                <span v-if="learners.length" class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">{{ learners.length }} learners</span>
            </div>

            <EmptyState v-if="!learners.length" title="No learners available" message="No learners are currently assigned to you for reports." />

            <div v-else class="overflow-hidden rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Learner</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Exports</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(learner, index) in learners" :key="learner.public_id" class="group transition-colors duration-150 hover:bg-orange-50/40 rp-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-xs font-bold">{{ (learner.name ?? '?').charAt(0).toUpperCase() }}</div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-text group-hover:text-orange-600 transition-colors">{{ learner.learner_code }}</p>
                                        <p class="text-[11px] font-medium text-muted">{{ learner.name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a class="inline-flex items-center gap-1.5 rounded-lg bg-orange-500 px-3 py-1.5 text-[11px] font-bold text-white transition-all duration-200 hover:bg-orange-600 hover:shadow-sm active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/diagnostic`">
                                        <Download class="size-3" /> Diagnostic
                                    </a>
                                    <a class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background px-3 py-1.5 text-[11px] font-bold text-text transition-all duration-200 hover:bg-slate-50 hover:border-border active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/module-progress`">
                                        Module
                                    </a>
                                    <a class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-background px-3 py-1.5 text-[11px] font-bold text-text transition-all duration-200 hover:bg-slate-50 hover:border-border active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/full-progress`">
                                        Full
                                    </a>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a class="group/link inline-flex items-center gap-1.5 text-[12px] font-bold text-slate-400 transition-colors hover:text-orange-500" href="/teacher/reports/pdf-placeholder">
                                    PDF later
                                    <ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>

<style scoped>
.rp-card-in { animation: rp-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes rp-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.rp-row-in { animation: rp-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes rp-row { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: translateX(0); } }
</style>
