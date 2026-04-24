<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({ learner: Object, moduleAttempts: Array });
</script>

<template>
    <TeacherLayout>
        <PageHeader :title="`Module Progress · ${learner.name}`" :subtitle="learner.learner_code" />
        <div v-if="moduleAttempts.length" class="grid gap-6">
            <DashboardCard v-for="attempt in moduleAttempts" :key="attempt.public_id">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-black text-text">{{ attempt.module }}</h2>
                        <p class="text-sm text-muted">{{ attempt.started_at ?? '-' }} → {{ attempt.completed_at ?? '-' }}</p>
                    </div>
                    <StatusBadge :status="attempt.status" />
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <ScoreCard label="Mastery Score" :value="attempt.score ?? '-'" suffix="%" />
                    <ScoreCard label="Correct" :value="attempt.correct_count" />
                    <ScoreCard label="Needs Retry" :value="attempt.incorrect_count" />
                    <ScoreCard label="Decision" :value="attempt.mastery_decision ?? '-'" />
                </div>
                <p class="mt-4 text-sm font-bold text-muted">Rule applied: {{ attempt.rule_applied ?? '-' }}</p>
                <DataTable class="mt-4" :headers="['activity_type', 'prompt', 'answer', 'expected_answer', 'is_correct', 'score', 'feedback_text', 'retry_count', 'is_mastery_item']" :rows="attempt.responses" />
            </DashboardCard>
        </div>
        <EmptyState v-else title="No module attempts yet" message="Module progress appears after the learner starts an assigned module." />
    </TeacherLayout>
</template>
