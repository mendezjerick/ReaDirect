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
                <DataTable class="mt-4" :headers="['activity_type', 'prompt', 'answer', 'expected_answer', 'transcript_source', 'audio_status', 'is_correct', 'score', 'feedback_text', 'retry_count', 'is_mastery_item']" :rows="attempt.responses" />
                <div v-if="attempt.responses.some((response) => response.audio)" class="mt-5 overflow-x-auto rounded-2xl border border-border">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-primaryLight text-xs uppercase text-primaryDark">
                            <tr>
                                <th class="px-4 py-3">Activity</th>
                                <th class="px-4 py-3">Transcript Source</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Size</th>
                                <th class="px-4 py-3">Playback</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="response in attempt.responses.filter((row) => row.audio)" :key="`${response.activity_type}-${response.prompt}`">
                                <td class="px-4 py-3 font-bold text-text">{{ response.activity_type }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.transcript_source ?? 'manual' }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.audio.mime_type ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.audio.file_size ?? '-' }} bytes</td>
                                <td class="px-4 py-3">
                                    <audio controls class="h-9 w-56" :src="response.audio.play_url" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DashboardCard>
        </div>
        <EmptyState v-else title="No module attempts yet" message="Module progress appears after the learner starts an assigned module." />
    </TeacherLayout>
</template>
