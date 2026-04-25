<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';

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

        <div class="grid gap-4 md:grid-cols-4">
            <ScoreCard label="CRLA Total" :value="diagnosticSummary?.crla_total_score ?? '-'" />
            <ScoreCard label="CRLA Level" :value="diagnosticSummary?.crla_classification ?? '-'" />
            <ScoreCard label="Final Reading Score" :value="readingSummary?.final_reading_score ?? '-'" />
            <ScoreCard label="Reading Classification" :value="readingSummary?.reading_classification ?? '-'" />
        </div>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Final Reassessment</h2>
            <div v-if="latestFinalReassessment" class="mt-4 grid gap-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <ScoreCard label="Final CRLA" :value="latestFinalReassessment.crla_total_score ?? '-'" />
                    <ScoreCard label="CRLA Growth" :value="finalComparison?.deltas?.crla_total_score ?? '-'" />
                    <ScoreCard label="Final Reading" :value="latestFinalReassessment.final_reading_score ?? '-'" />
                    <ScoreCard label="Reading Growth" :value="finalComparison?.deltas?.final_reading_score ?? '-'" />
                </div>
                <p class="text-sm font-bold text-muted">{{ finalComparison?.summary }}</p>
                <Link class="font-black text-primary" :href="`/teacher/learners/${learner.public_id}/assessments/${latestFinalReassessment.public_id}`">Review final reassessment</Link>
            </div>
            <EmptyState v-else title="No final reassessment yet" message="Final results appear after the learner completes the final check." />
        </DashboardCard>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <DashboardCard>
                <h2 class="text-xl font-black text-text">Diagnostic Summary</h2>
                <div v-if="latestDiagnosticAttempt" class="mt-4 grid gap-3">
                    <p class="text-sm font-bold text-muted">Reading classification is based only on final_reading_score.</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <ScoreCard label="Task 1" :value="diagnosticSummary.task_1_score ?? '-'" />
                        <ScoreCard label="Task 2A" :value="diagnosticSummary.task_2a_score ?? '-'" />
                        <ScoreCard label="Task 2B" :value="diagnosticSummary.task_2b_score ?? '-'" />
                    </div>
                    <Link class="font-black text-primary" :href="`/teacher/learners/${learner.public_id}/assessments/${latestDiagnosticAttempt.public_id}`">Review assessment</Link>
                </div>
                <EmptyState v-else title="No diagnostic yet" />
            </DashboardCard>

            <DashboardCard>
                <h2 class="text-xl font-black text-text">Module Progress</h2>
                <div v-if="moduleProgress.length" class="mt-4 grid gap-3">
                    <div v-for="attempt in moduleProgress" :key="attempt.completed_at ?? attempt.module" class="rounded-xl bg-background p-4">
                        <p class="font-black text-text">{{ attempt.module }}</p>
                        <p class="text-sm text-muted">{{ attempt.status }} · {{ attempt.mastery_decision ?? 'No decision yet' }}</p>
                    </div>
                    <Link class="font-black text-primary" :href="`/teacher/learners/${learner.public_id}/modules`">Review module progress</Link>
                </div>
                <EmptyState v-else title="No module attempts yet" />
            </DashboardCard>
        </div>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Recommendation</h2>
            <p v-if="latestRecommendation" class="mt-3 text-muted">{{ latestRecommendation.reason }}</p>
            <StatusBadge v-if="latestRecommendation" class="mt-3" :status="latestRecommendation.rule_applied" />
            <EmptyState v-else title="No recommendation yet" />
        </DashboardCard>

        <DashboardCard class="mt-6">
            <div class="flex flex-wrap gap-3">
                <a class="rounded-xl bg-primary px-4 py-2 font-black text-white" :href="`/teacher/reports/learner/${learner.public_id}/diagnostic`">Diagnostic CSV</a>
                <a class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary" :href="`/teacher/reports/learner/${learner.public_id}/module-progress`">Module CSV</a>
                <a class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary" :href="`/teacher/reports/learner/${learner.public_id}/full-progress`">Full CSV</a>
                <a class="rounded-xl bg-primary-light px-4 py-2 font-black text-primary" :href="`/teacher/reports/learner/${learner.public_id}/final-comparison`">Final Comparison CSV</a>
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
