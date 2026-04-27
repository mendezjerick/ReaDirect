<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { FileText, Download } from 'lucide-vue-next';

defineProps({ reports: Array, learners: Array });
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Reports" subtitle="CSV exports and PDF placeholders" />

        <!-- Report cards -->
        <div class="grid gap-4 sm:grid-cols-2">
            <DashboardCard v-for="report in reports" :key="report.type">
                <div class="flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                        <FileText :size="15" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-text">{{ report.title }}</h2>
                        <p class="mt-1 text-[12px] text-muted leading-relaxed">{{ report.description }}</p>
                    </div>
                </div>
            </DashboardCard>
        </div>

        <!-- Learner reports table -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                    <FileText :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Learner Reports</h2>
            </div>
            <div v-if="learners.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Learner</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Exports</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="learner in learners" :key="learner.public_id" class="border-t border-border/40 transition-colors hover:bg-background/60">
                            <td class="px-4 py-3 text-[13px] font-bold text-text">{{ learner.learner_code }} · {{ learner.name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a class="inline-flex items-center gap-1.5 rounded-lg bg-orange-500 px-3 py-1.5 text-[12px] font-bold text-white transition-colors hover:bg-orange-600" :href="`/teacher/reports/learner/${learner.public_id}/diagnostic`">
                                        <Download :size="12" />Diagnostic CSV
                                    </a>
                                    <a class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-surface px-3 py-1.5 text-[12px] font-bold text-text transition-colors hover:bg-background" :href="`/teacher/reports/learner/${learner.public_id}/module-progress`">
                                        Module CSV
                                    </a>
                                    <a class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-surface px-3 py-1.5 text-[12px] font-bold text-text transition-colors hover:bg-background" :href="`/teacher/reports/learner/${learner.public_id}/full-progress`">
                                        Full CSV
                                    </a>
                                </div>
                            </td>
                            <td class="px-4 py-3"><a class="text-[12px] text-muted hover:text-primary transition-colors" href="/teacher/reports/pdf-placeholder">PDF later</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No learners available" />
        </DashboardCard>

        <!-- Class summary export -->
        <DashboardCard class="mt-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                        <Download :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Class Summary</h2>
                </div>
                <a class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-[13px] font-bold text-white transition-colors hover:bg-orange-600" href="/teacher/reports/class-summary">
                    <Download :size="14" />Export Class CSV
                </a>
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
