<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({ learner: Object, moduleAttempts: Array });

const transcriptDrafts = reactive({});

const transcriptValue = (response) => {
    const key = response.audio.public_id;

    if (!(key in transcriptDrafts)) {
        transcriptDrafts[key] = response.audio.transcript ?? response.learner_transcript ?? response.answer ?? '';
    }

    return transcriptDrafts[key];
};

const updateTranscript = (response, value) => {
    transcriptDrafts[response.audio.public_id] = value;
};

const saveTranscript = (response) => {
    router.put(response.audio.transcript_update_url, {
        transcript: transcriptDrafts[response.audio.public_id] ?? '',
    }, {
        preserveScroll: true,
    });
};
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
                <DataTable class="mt-4" :headers="['activity_type', 'prompt', 'answer', 'expected_answer', 'transcript_source', 'stt_confidence', 'audio_status', 'is_correct', 'score', 'feedback_text', 'retry_count', 'is_mastery_item']" :rows="attempt.responses" />
                <div v-if="attempt.responses.some((response) => response.audio)" class="mt-5 overflow-x-auto rounded-2xl border border-border">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-primaryLight text-xs uppercase text-primaryDark">
                            <tr>
                                <th class="px-4 py-3">Activity</th>
                                <th class="px-4 py-3">Transcript Source</th>
                                <th class="px-4 py-3">Transcript</th>
                                <th class="px-4 py-3">Confidence</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Size</th>
                                <th class="px-4 py-3">Playback</th>
                                <th class="px-4 py-3">Teacher Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="response in attempt.responses.filter((row) => row.audio)" :key="`${response.activity_type}-${response.prompt}`">
                                <td class="px-4 py-3 font-bold text-text">{{ response.activity_type }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.transcript_source ?? 'manual' }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.audio.transcript ?? response.learner_transcript ?? response.answer ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.audio.stt_confidence !== null && response.audio.stt_confidence !== undefined ? `${Math.round(response.audio.stt_confidence * 100)}%` : '-' }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.audio.mime_type ?? '-' }}</td>
                                <td class="px-4 py-3 text-muted">{{ response.audio.file_size ?? '-' }} bytes</td>
                                <td class="px-4 py-3">
                                    <audio controls class="h-9 w-56" :src="response.audio.play_url" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="grid min-w-64 gap-2">
                                        <textarea
                                            class="min-h-20 rounded-xl border border-border px-3 py-2 text-sm text-text focus:border-primary focus:outline-none"
                                            :value="transcriptValue(response)"
                                            @input="updateTranscript(response, $event.target.value)"
                                        />
                                        <button type="button" class="rounded-xl bg-primary px-3 py-2 text-xs font-black text-white" @click="saveTranscript(response)">
                                            Save transcript
                                        </button>
                                    </div>
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
