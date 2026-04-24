<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    learner: Object,
    assessmentAttempt: Object,
    task1Responses: Array,
    task2aResponses: Array,
    task2bResponses: Array,
    passageResult: Object,
    comprehensionResponses: Array,
    scoringSummary: Object,
    placementDecision: Object,
});
</script>

<template>
    <TeacherLayout>
        <PageHeader :title="`Assessment Review · ${learner.name}`" :subtitle="learner.learner_code" />
        <div class="grid gap-4 md:grid-cols-4">
            <ScoreCard label="CRLA Total" :value="scoringSummary.crla_total_score ?? '-'" />
            <ScoreCard label="CRLA Level" :value="scoringSummary.crla_classification ?? '-'" />
            <ScoreCard label="Final Reading Score" :value="scoringSummary.final_reading_score ?? '-'" />
            <ScoreCard label="Reading Classification" :value="scoringSummary.reading_classification ?? '-'" />
        </div>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Attempt Metadata</h2>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                <StatusBadge :status="assessmentAttempt.status" />
                <p class="text-sm text-muted">Started: {{ assessmentAttempt.started_at ?? '-' }}</p>
                <p class="text-sm text-muted">Completed: {{ assessmentAttempt.completed_at ?? '-' }}</p>
            </div>
        </DashboardCard>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">CRLA Responses</h2>
            <div class="mt-4 grid gap-6">
                <section>
                    <h3 class="mb-2 font-black text-text">Task 1 Letter Pronunciation</h3>
                    <DataTable v-if="task1Responses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'is_correct', 'score']" :rows="task1Responses" />
                    <EmptyState v-else title="No Task 1 responses" />
                </section>
                <section>
                    <h3 class="mb-2 font-black text-text">Task 2A Rhyming Words</h3>
                    <DataTable v-if="task2aResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'is_correct', 'score']" :rows="task2aResponses" />
                    <EmptyState v-else title="Task 2A was skipped or not completed" />
                </section>
                <section>
                    <h3 class="mb-2 font-black text-text">Task 2B Word-in-Sentence</h3>
                    <DataTable v-if="task2bResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'is_correct', 'score']" :rows="task2bResponses" />
                    <EmptyState v-else title="No Task 2B responses" />
                </section>
            </div>
        </DashboardCard>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Reading Comprehension</h2>
            <p class="mt-2 text-sm font-bold text-muted">Reading classification is displayed from final_reading_score only.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <ScoreCard label="Incorrect Words" :value="passageResult.incorrect_words ?? '-'" />
                <ScoreCard label="Reading Accuracy" :value="scoringSummary.reading_accuracy ?? '-'" suffix="%" />
                <ScoreCard label="Comprehension" :value="scoringSummary.comprehension_percentage ?? '-'" suffix="%" />
            </div>
            <div class="mt-4">
                <DataTable v-if="comprehensionResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'is_correct', 'score']" :rows="comprehensionResponses" />
                <EmptyState v-else title="No comprehension responses" />
            </div>
        </DashboardCard>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Module Placement</h2>
            <p class="mt-2 text-muted">{{ placementDecision.reason ?? 'No placement decision yet.' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <StatusBadge :status="placementDecision.module ?? 'No module needed'" />
                <StatusBadge v-if="placementDecision.rule_applied" :status="placementDecision.rule_applied" variant="success" />
            </div>
            <p class="mt-4 text-sm font-bold text-muted">Audio playback placeholder: no audio files are attached to this review yet.</p>
        </DashboardCard>
    </TeacherLayout>
</template>
