<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import {
    TrendingUp,
    Award,
    BookOpen,
    BarChart2,
    ClipboardCheck,
    FileText,
    Download,
    ArrowRight,
} from 'lucide-vue-next';

defineProps({
    learner: Object,
    latestDiagnosticAttempt: Object,
    diagnosticSummary: Object,
    readingSummary: Object,
    latestFinalReassessment: Object,
    finalComparison: Object,
    moduleProgress: Array,
    latestRecommendation: Object,
    recentActivity: Array,
});
</script>

<template>
    <TeacherLayout>
        <PageHeader :title="learner.name" :subtitle="`${learner.learner_code} · ${learner.class ?? 'No class'}`" />

        <!-- Top score cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ScoreCard label="CRLA Total"             :value="diagnosticSummary?.crla_total_score ?? '-'"      :icon="TrendingUp"     color="blue"   />
            <ScoreCard label="CRLA Level"             :value="diagnosticSummary?.crla_classification ?? '-'"   :icon="BarChart2"      color="green"  />
            <ScoreCard label="Final Reading Score"    :value="readingSummary?.final_reading_score ?? '-'"      :icon="BookOpen"       color="purple" />
            <ScoreCard label="Reading Classification" :value="readingSummary?.reading_classification ?? '-'"   :icon="Award"          color="orange" />
        </div>

        <!-- Final reassessment -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                    <Award :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Final Reassessment</h2>
            </div>
            <div v-if="latestFinalReassessment" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <ScoreCard label="Final CRLA"     :value="latestFinalReassessment.crla_total_score ?? '-'"   color="blue"   />
                    <ScoreCard label="CRLA Growth"    :value="finalComparison?.deltas?.crla_total_score ?? '-'"   color="green"  />
                    <ScoreCard label="Final Reading"   :value="latestFinalReassessment.final_reading_score ?? '-'" color="purple" />
                    <ScoreCard label="Reading Growth"  :value="finalComparison?.deltas?.final_reading_score ?? '-'" color="orange" />
                </div>
                <p class="text-[13px] font-medium text-muted">{{ finalComparison?.summary }}</p>
                <Link class="inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors" :href="`/teacher/learners/${learner.public_id}/assessments/${latestFinalReassessment.public_id}`">
                    Review final reassessment <ArrowRight :size="14" />
                </Link>
            </div>
            <EmptyState v-else title="No final reassessment yet" message="Final results appear after the learner completes the final check." />
        </DashboardCard>

        <!-- Diagnostic + Module progress -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <!-- Diagnostic summary -->
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                        <ClipboardCheck :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Diagnostic Summary</h2>
                </div>
                <div v-if="latestDiagnosticAttempt" class="space-y-4">
                    <p class="text-[12px] text-muted">Reading classification is based only on final_reading_score.</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <ScoreCard label="Task 1"  :value="diagnosticSummary.task_1_score ?? '-'"  color="blue"   />
                        <ScoreCard label="Task 2A" :value="diagnosticSummary.task_2a_score ?? '-'" color="green"  />
                        <ScoreCard label="Task 2B" :value="diagnosticSummary.task_2b_score ?? '-'" color="purple" />
                    </div>
                    <Link class="inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors" :href="`/teacher/learners/${learner.public_id}/assessments/${latestDiagnosticAttempt.public_id}`">
                        Review assessment <ArrowRight :size="14" />
                    </Link>
                </div>
                <EmptyState v-else title="No diagnostic yet" />
            </DashboardCard>

            <!-- Module progress -->
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <BookOpen :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Module Progress</h2>
                </div>
                <div v-if="moduleProgress.length" class="space-y-3">
                    <div v-for="attempt in moduleProgress" :key="attempt.completed_at ?? attempt.module" class="rounded-xl bg-background px-4 py-3 transition-colors hover:bg-blue-50/40">
                        <p class="text-[13px] font-bold text-text">{{ attempt.module }}</p>
                        <p class="text-[12px] text-muted">{{ attempt.status }} · {{ attempt.mastery_decision ?? 'No decision yet' }}</p>
                    </div>
                    <Link class="inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors" :href="`/teacher/learners/${learner.public_id}/modules`">
                        Review module progress <ArrowRight :size="14" />
                    </Link>
                </div>
                <EmptyState v-else title="No module attempts yet" />
            </DashboardCard>
        </div>

        <!-- Recommendation -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <FileText :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Recommendation</h2>
            </div>
            <div v-if="latestRecommendation">
                <p class="text-[13px] text-muted leading-relaxed">{{ latestRecommendation.reason }}</p>
                <StatusBadge class="mt-3" :status="latestRecommendation.rule_applied" variant="success" />
            </div>
            <EmptyState v-else title="No recommendation yet" />
        </DashboardCard>

        <!-- Export links -->
        <DashboardCard class="mt-6">
            <div class="flex flex-wrap gap-2">
                <a class="inline-flex items-center gap-1.5 rounded-xl bg-orange-500 px-4 py-2.5 text-[13px] font-bold text-white transition-colors hover:bg-orange-600" :href="`/teacher/reports/learner/${learner.public_id}/diagnostic`">
                    <Download :size="14" />Diagnostic CSV
                </a>
                <a class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-surface px-4 py-2.5 text-[13px] font-bold text-text transition-colors hover:bg-background" :href="`/teacher/reports/learner/${learner.public_id}/module-progress`">
                    Module CSV
                </a>
                <a class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-surface px-4 py-2.5 text-[13px] font-bold text-text transition-colors hover:bg-background" :href="`/teacher/reports/learner/${learner.public_id}/full-progress`">
                    Full CSV
                </a>
                <a class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-surface px-4 py-2.5 text-[13px] font-bold text-text transition-colors hover:bg-background" :href="`/teacher/reports/learner/${learner.public_id}/final-comparison`">
                    Final Comparison CSV
                </a>
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
