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
import {
    BookOpen,
    Award,
    CheckCircle2,
    XCircle,
    Headphones,
    Save,
} from 'lucide-vue-next';

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

        <div v-if="moduleAttempts.length" class="space-y-6">
            <DashboardCard v-for="attempt in moduleAttempts" :key="attempt.public_id">
                <!-- Module header -->
                <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                            <BookOpen :size="15" />
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-text">{{ attempt.module }}</h2>
                            <p class="text-[11px] text-muted">{{ attempt.started_at ?? '-' }} → {{ attempt.completed_at ?? '-' }}</p>
                        </div>
                    </div>
                    <StatusBadge :status="attempt.status" />
                </div>

                <!-- Score cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <ScoreCard label="Mastery Score"  :value="attempt.score ?? '-'" suffix="%"  :icon="Award"        color="blue"   />
                    <ScoreCard label="Correct"        :value="attempt.correct_count"             :icon="CheckCircle2" color="green"  />
                    <ScoreCard label="Needs Retry"    :value="attempt.incorrect_count"            :icon="XCircle"      color="orange" />
                    <ScoreCard label="Decision"       :value="attempt.mastery_decision ?? '-'"    :icon="Award"        color="purple" />
                </div>

                <p class="mt-4 text-[12px] font-medium text-muted">Rule applied: {{ attempt.rule_applied ?? '-' }}</p>

                <!-- Response data -->
                <div class="mt-4">
                    <DataTable :headers="['activity_type', 'prompt', 'answer', 'expected_answer', 'transcript_source', 'stt_confidence', 'audio_status', 'is_correct', 'score', 'feedback_text', 'retry_count', 'is_mastery_item']" :rows="attempt.responses" />
                </div>

                <!-- Audio review -->
                <div v-if="attempt.responses.some((response) => response.audio)" class="mt-5 overflow-x-auto rounded-2xl border border-border/60">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-border/60 bg-background">
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Activity</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Transcript Source</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Transcript</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Confidence</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Type</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Size</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Playback</th>
                                <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Teacher Review</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr v-for="response in attempt.responses.filter((row) => row.audio)" :key="`${response.activity_type}-${response.prompt}`" class="transition-colors hover:bg-background/60">
                                <td class="px-4 py-3 text-[13px] font-bold text-text">{{ response.activity_type }}</td>
                                <td class="px-4 py-3 text-[13px] text-slate-600">{{ response.transcript_source ?? 'manual' }}</td>
                                <td class="px-4 py-3 text-[13px] text-slate-600">{{ response.audio.transcript ?? response.learner_transcript ?? response.answer ?? '-' }}</td>
                                <td class="px-4 py-3 text-[13px] text-slate-600">{{ response.audio.stt_confidence !== null && response.audio.stt_confidence !== undefined ? `${Math.round(response.audio.stt_confidence * 100)}%` : '-' }}</td>
                                <td class="px-4 py-3 text-[13px] text-slate-600">{{ response.audio.mime_type ?? '-' }}</td>
                                <td class="px-4 py-3 text-[13px] text-slate-600">{{ response.audio.file_size ?? '-' }} bytes</td>
                                <td class="px-4 py-3">
                                    <audio controls class="h-9 w-56" :src="response.audio.play_url" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="grid min-w-64 gap-2">
                                        <textarea
                                            class="min-h-20 rounded-xl border border-border/60 bg-background px-3 py-2 text-[13px] text-text focus:border-primary focus:bg-surface focus:outline-none"
                                            :value="transcriptValue(response)"
                                            @input="updateTranscript(response, $event.target.value)"
                                        />
                                        <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-orange-500 px-3 py-2 text-[12px] font-bold text-white transition-colors hover:bg-orange-600" @click="saveTranscript(response)">
                                            <Save :size="12" />Save transcript
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
