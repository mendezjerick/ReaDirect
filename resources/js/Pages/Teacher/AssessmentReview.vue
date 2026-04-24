<script setup>
import { computed } from 'vue';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
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

const audioRows = computed(() => [
    ...props.task1Responses.map((row) => ({ task: 'Task 1', ...row })),
    ...props.task2aResponses.map((row) => ({ task: 'Task 2A', ...row })),
    ...props.task2bResponses.map((row) => ({ task: 'Task 2B', ...row })),
    ...props.comprehensionResponses.map((row) => ({ task: 'Comprehension', ...row })),
].filter((row) => row.audio));
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
                    <DataTable v-if="task1Responses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="task1Responses" />
                    <EmptyState v-else title="No Task 1 responses" />
                </section>
                <section>
                    <h3 class="mb-2 font-black text-text">Task 2A Rhyming Words</h3>
                    <DataTable v-if="task2aResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="task2aResponses" />
                    <EmptyState v-else title="Task 2A was skipped or not completed" />
                </section>
                <section>
                    <h3 class="mb-2 font-black text-text">Task 2B Word-in-Sentence</h3>
                    <DataTable v-if="task2bResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="task2bResponses" />
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
                <DataTable v-if="comprehensionResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="comprehensionResponses" />
                <EmptyState v-else title="No comprehension responses" />
            </div>
        </DashboardCard>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Audio Review</h2>
            <div v-if="audioRows.length" class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-primaryLight text-xs uppercase text-primaryDark">
                        <tr>
                            <th class="px-4 py-3">Task</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Transcript Source</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Playback</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in audioRows" :key="`${row.task}-${row.item}`">
                            <td class="px-4 py-3 font-bold text-text">{{ row.task }}</td>
                            <td class="px-4 py-3 text-muted">{{ row.item }}</td>
                            <td class="px-4 py-3 text-muted">{{ row.transcript_source ?? 'manual' }}</td>
                            <td class="px-4 py-3 text-muted">{{ row.audio.mime_type ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted">{{ row.audio.file_size ?? '-' }} bytes</td>
                            <td class="px-4 py-3">
                                <audio controls class="h-9 w-56" :src="row.audio.play_url" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No audio recordings yet" message="Recordings appear here after learner tasks include saved audio." />
        </DashboardCard>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Module Placement</h2>
            <p class="mt-2 text-muted">{{ placementDecision.reason ?? 'No placement decision yet.' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <StatusBadge :status="placementDecision.module ?? 'No module needed'" />
                <StatusBadge v-if="placementDecision.rule_applied" :status="placementDecision.rule_applied" variant="success" />
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
