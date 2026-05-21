<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
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
    ArrowLeft
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
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/teacher/learners" class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-extrabold text-text">{{ learner.name }}</h1>
                    <p class="mt-1 text-sm font-medium text-muted">{{ learner.learner_code }} &bull; {{ learner.class ?? 'No class' }}</p>
                </div>
            </div>
        </div>

        <!-- Top score cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 in-card" style="--delay: 0ms">
            <ScoreCard label="CRLA Total"             :value="diagnosticSummary?.crla_total_score ?? '-'"      :icon="TrendingUp"     color="blue"   />
            <ScoreCard label="CRLA Level"             :value="diagnosticSummary?.crla_classification ?? '-'"   :icon="BarChart2"      color="green"  />
            <ScoreCard label="Final Reading Score"    :value="readingSummary?.final_reading_score ?? '-'"      :icon="BookOpen"       color="purple" />
            <ScoreCard label="Reading Classification" :value="readingSummary?.reading_classification ?? '-'"   :icon="Award"          color="orange" />
        </div>

        <!-- Final reassessment -->
        <DashboardCard class="mt-6 in-card" style="--delay: 100ms">
            <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                    <Award class="size-4" />
                </div>
                <h2 class="text-[15px] font-bold text-text">Final Reassessment</h2>
            </div>
            <div v-if="latestFinalReassessment" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <ScoreCard label="Final CRLA"     :value="latestFinalReassessment.crla_total_score ?? '-'"   color="blue"   />
                    <ScoreCard label="CRLA Growth"    :value="finalComparison?.deltas?.crla_total_score ?? '-'"   color="green"  />
                    <ScoreCard label="Final Reading"   :value="latestFinalReassessment.final_reading_score ?? '-'" color="purple" />
                    <ScoreCard label="Reading Growth"  :value="finalComparison?.deltas?.final_reading_score ?? '-'" color="orange" />
                </div>
                <p class="text-[13px] font-medium text-muted rounded-xl bg-surface p-4">{{ finalComparison?.summary }}</p>
                <Link class="inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors" :href="`/teacher/learners/${learner.public_id}/assessments/${latestFinalReassessment.public_id}`">
                    Review final reassessment <ArrowRight class="size-4" />
                </Link>
            </div>
            <EmptyState v-else title="No final reassessment yet" message="Final results appear after the learner completes the final check." />
        </DashboardCard>

        <!-- Diagnostic + Module progress -->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Diagnostic summary -->
            <DashboardCard class="in-card" style="--delay: 150ms">
                <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                        <ClipboardCheck class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Diagnostic Summary</h2>
                </div>
                <div v-if="latestDiagnosticAttempt" class="space-y-5">
                    <p class="text-[12px] font-medium text-muted rounded-lg bg-blue-50/50 p-3 text-blue-800">Reading classification is based only on final_reading_score.</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <ScoreCard label="Task 1"  :value="diagnosticSummary.task_1_score ?? '-'"  color="blue"   />
                        <ScoreCard label="Task 2A" :value="diagnosticSummary.task_2a_score ?? '-'" color="green"  />
                        <ScoreCard label="Task 2B" :value="diagnosticSummary.task_2b_score ?? '-'" color="purple" />
                    </div>
                    <Link class="inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors" :href="`/teacher/learners/${learner.public_id}/assessments/${latestDiagnosticAttempt.public_id}`">
                        Review assessment <ArrowRight class="size-4" />
                    </Link>
                </div>
                <EmptyState v-else title="No diagnostic yet" />
            </DashboardCard>

            <!-- Module progress -->
            <DashboardCard class="in-card" style="--delay: 200ms">
                <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <BookOpen class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Module Progress</h2>
                </div>
                <div v-if="moduleProgress.length" class="space-y-3">
                    <div v-for="attempt in moduleProgress" :key="attempt.completed_at ?? attempt.module" class="rounded-xl border border-border/40 bg-surface p-3 transition-colors hover:border-border/80">
                        <p class="text-[13px] font-bold text-text">{{ attempt.module }}</p>
                        <p class="mt-0.5 text-[12px] font-medium text-muted">{{ attempt.status }} &bull; {{ attempt.mastery_decision ?? 'No decision yet' }}</p>
                    </div>
                    <div class="pt-2">
                        <Link class="inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 hover:text-orange-600 transition-colors" :href="`/teacher/learners/${learner.public_id}/modules`">
                            Review module progress <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
                <EmptyState v-else title="No module attempts yet" />
            </DashboardCard>
        </div>

        <!-- Recommendation -->
        <DashboardCard class="mt-6 in-card" style="--delay: 250ms">
            <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <FileText class="size-4" />
                </div>
                <h2 class="text-[15px] font-bold text-text">Recommendation</h2>
            </div>
            <div v-if="latestRecommendation" class="space-y-4">
                <p class="text-[13px] font-medium text-text leading-relaxed">{{ latestRecommendation.reason }}</p>
                <StatusBadge :status="latestRecommendation.rule_applied" variant="success" />
            </div>
            <EmptyState v-else title="No recommendation yet" />
        </DashboardCard>

        <!-- Export links -->
        <DashboardCard class="mt-6 in-card" style="--delay: 300ms">
            <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    <Download class="size-4" />
                </div>
                <h2 class="text-[15px] font-bold text-text">Export Reports</h2>
            </div>
            <div class="flex flex-wrap gap-3">
                <a class="inline-flex items-center gap-1.5 rounded-xl bg-orange-500 px-5 py-2.5 text-[13px] font-bold text-white transition-all hover:bg-orange-600 hover:shadow-md hover:shadow-orange-500/20 active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/diagnostic`">
                    <Download class="size-4" />Diagnostic CSV
                </a>
                <a class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-surface px-5 py-2.5 text-[13px] font-bold text-text transition-all hover:bg-slate-100 hover:border-slate-300 active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/module-progress`">
                    Module CSV
                </a>
                <a class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-surface px-5 py-2.5 text-[13px] font-bold text-text transition-all hover:bg-slate-100 hover:border-slate-300 active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/full-progress`">
                    Full CSV
                </a>
                <a class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-surface px-5 py-2.5 text-[13px] font-bold text-text transition-all hover:bg-slate-100 hover:border-slate-300 active:scale-[0.97]" :href="`/teacher/reports/learner/${learner.public_id}/final-comparison`">
                    Final Comparison CSV
                </a>
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>

<style scoped>
.in-card { animation: entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--delay, 0ms); }
@keyframes entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
