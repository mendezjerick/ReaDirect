<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import {
    TrendingUp,
    BarChart2,
    BookOpen,
    Award,
    ClipboardCheck,
    BookMarked,
    Headphones,
    Save,
} from 'lucide-vue-next';

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

const transcriptDrafts = reactive({});

const transcriptValue = (row) => {
    const key = row.audio.public_id;

    if (!(key in transcriptDrafts)) {
        transcriptDrafts[key] = row.audio.transcript ?? row.answer ?? '';
    }

    return transcriptDrafts[key];
};

const updateTranscript = (row, value) => {
    transcriptDrafts[row.audio.public_id] = value;
};

const saveTranscript = (row) => {
    router.put(row.audio.transcript_update_url, {
        transcript: transcriptDrafts[row.audio.public_id] ?? '',
    }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <TeacherLayout>
        <PageHeader :title="`Assessment Review · ${learner.name}`" :subtitle="learner.learner_code" />

        <!-- Score cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ScoreCard label="CRLA Total"             :value="scoringSummary.crla_total_score ?? '-'"        :icon="TrendingUp" color="blue"   />
            <ScoreCard label="CRLA Level"             :value="scoringSummary.crla_classification ?? '-'"     :icon="BarChart2"  color="green"  />
            <ScoreCard label="Final Reading Score"    :value="scoringSummary.final_reading_score ?? '-'"     :icon="BookOpen"   color="purple" />
            <ScoreCard label="Reading Classification" :value="scoringSummary.reading_classification ?? '-'"  :icon="Award"      color="orange" />
        </div>

        <!-- Attempt metadata -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                    <ClipboardCheck :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Attempt Metadata</h2>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <StatusBadge :status="assessmentAttempt.status" />
                <p class="text-[13px] text-muted">Started: {{ assessmentAttempt.started_at ?? '-' }}</p>
                <p class="text-[13px] text-muted">Completed: {{ assessmentAttempt.completed_at ?? '-' }}</p>
            </div>
        </DashboardCard>

        <!-- CRLA Responses -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                    <BookMarked :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">CRLA Responses</h2>
            </div>
            <div class="space-y-6">
                <section>
                    <h3 class="mb-3 text-[13px] font-bold text-text">Task 1 Letter Pronunciation</h3>
                    <DataTable v-if="task1Responses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="task1Responses" />
                    <EmptyState v-else title="No Task 1 responses" />
                </section>
                <section>
                    <h3 class="mb-3 text-[13px] font-bold text-text">Task 2A Rhyming Words</h3>
                    <DataTable v-if="task2aResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="task2aResponses" />
                    <EmptyState v-else title="Task 2A was skipped or not completed" />
                </section>
                <section>
                    <h3 class="mb-3 text-[13px] font-bold text-text">Task 2B Word-in-Sentence</h3>
                    <DataTable v-if="task2bResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="task2bResponses" />
                    <EmptyState v-else title="No Task 2B responses" />
                </section>
            </div>
        </DashboardCard>

        <!-- Reading Comprehension -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                    <BookOpen :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Reading Comprehension</h2>
            </div>
            <p class="mb-4 text-[12px] text-muted">Reading classification is displayed from final_reading_score only.</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <ScoreCard label="Incorrect Words"   :value="passageResult.incorrect_words ?? '-'"             color="orange" />
                <ScoreCard label="Reading Accuracy"  :value="scoringSummary.reading_accuracy ?? '-'"  suffix="%" color="blue"   />
                <ScoreCard label="Comprehension"     :value="scoringSummary.comprehension_percentage ?? '-'" suffix="%" color="green" />
            </div>
            <div class="mt-4">
                <DataTable v-if="comprehensionResponses.length" :headers="['item', 'prompt', 'expected_answer', 'answer', 'transcript_source', 'audio_status', 'is_correct', 'score']" :rows="comprehensionResponses" />
                <EmptyState v-else title="No comprehension responses" />
            </div>
        </DashboardCard>

        <!-- Audio Review -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                    <Headphones :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Audio Review</h2>
            </div>
            <div v-if="audioRows.length" class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Task</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Item</th>
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
                        <tr v-for="row in audioRows" :key="`${row.task}-${row.item}`" class="transition-colors hover:bg-background/60">
                            <td class="px-4 py-3 text-[13px] font-bold text-text">{{ row.task }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ row.item }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ row.transcript_source ?? 'manual' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ row.audio.transcript ?? row.answer ?? '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ row.audio.stt_confidence !== null && row.audio.stt_confidence !== undefined ? `${Math.round(row.audio.stt_confidence * 100)}%` : '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ row.audio.mime_type ?? '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ row.audio.file_size ?? '-' }} bytes</td>
                            <td class="px-4 py-3">
                                <audio controls class="h-9 w-56" :src="row.audio.play_url" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="grid min-w-64 gap-2">
                                    <textarea
                                        class="min-h-20 rounded-xl border border-border/60 bg-background px-3 py-2 text-[13px] text-text focus:border-primary focus:bg-surface focus:outline-none"
                                        :value="transcriptValue(row)"
                                        @input="updateTranscript(row, $event.target.value)"
                                    />
                                    <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-orange-500 px-3 py-2 text-[12px] font-bold text-white transition-colors hover:bg-orange-600" @click="saveTranscript(row)">
                                        <Save :size="12" />Save transcript
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No audio recordings yet" message="Recordings appear here after learner tasks include saved audio." />
        </DashboardCard>

        <!-- Module Placement -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <Award :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Module Placement</h2>
            </div>
            <p class="text-[13px] text-muted leading-relaxed">{{ placementDecision.reason ?? 'No placement decision yet.' }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <StatusBadge :status="placementDecision.module ?? 'No module needed'" />
                <StatusBadge v-if="placementDecision.rule_applied" :status="placementDecision.rule_applied" variant="success" />
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
