<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Activity, AlertTriangle, CheckCircle2, FileSearch, RefreshCcw, Search, XCircle } from 'lucide-vue-next';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AdminDebugPanel from '../../../Components/Admin/AdminDebugPanel.vue';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';

const props = defineProps({
    sections: Array,
    modules: Array,
    activityTypes: Array,
    initialItems: Array,
    routes: Object,
});

const section = ref(props.sections?.[0]?.key ?? 'diagnostic_letters');
const search = ref('');
const moduleId = ref('');
const activityType = ref('');
const items = ref(props.initialItems ?? []);
const selectedItemId = ref(items.value[0]?.id ?? '');
const loadingItems = ref(false);
const itemError = ref('');
const currentFile = ref(null);
const submitting = ref(false);
const result = ref(null);
const error = ref('');
const recorderResetKey = ref(0);

const selectedSection = computed(() => props.sections?.find((item) => item.key === section.value) ?? props.sections?.[0]);
const selectedItem = computed(() => items.value.find((item) => item.id === selectedItemId.value) ?? items.value[0] ?? null);
const normalizedResult = computed(() => result.value ? normalizeAsrResponse(result.value) : null);
const isModuleSection = computed(() => selectedSection.value?.source === 'module');
const expectedText = computed(() => String(selectedItem.value?.expected_text ?? '').trim());
const recorderPromptType = computed(() => {
    const type = String(selectedItem.value?.prompt_type ?? 'word');
    return type === 'reading_passage' ? 'passage' : type;
});
const compactDebug = computed(() => {
    if (!result.value) return null;

    return {
        expected_text: result.value.expected_text,
        raw_transcript: result.value.raw_transcript,
        corrected_transcript: result.value.corrected_transcript,
        displayed_transcript: result.value.displayed_transcript,
        accepted: result.value.accepted,
        retry_required: result.value.retry_required,
        uncertain: result.value.uncertain,
        correction_strategy_used: result.value.correction_strategy_used,
        dynamic_correction_reason: result.value.dynamic_correction_reason,
        variant_reason: result.value.variant_reason,
        gop_score: result.value.gop_score,
        gop_decision: result.value.gop_decision,
        phonetic_similarity_score: result.value.phonetic_similarity_score,
        dynamic_correction_confidence: result.value.dynamic_correction_confidence,
        dynamic_spelling_similarity: result.value.dynamic_spelling_similarity,
        dynamic_phoneme_similarity: result.value.dynamic_phoneme_similarity,
        asr_spelling_variant_confidence: result.value.asr_spelling_variant_confidence,
        consonant_skeleton_similarity: result.value.consonant_skeleton_similarity,
        vowel_tolerant_similarity: result.value.vowel_tolerant_similarity,
        expected_phoneme_coverage: result.value.expected_phoneme_coverage,
        raw_wer: result.value.raw_wer,
        corrected_wer: result.value.corrected_wer,
        raw_cer: result.value.raw_cer,
        corrected_cer: result.value.corrected_cer,
        scoring: result.value.scoring,
        word_alignment: result.value.word_alignment,
        audio_quality: result.value.audio_quality,
        pause_metrics: result.value.pause_metrics,
        debug_metadata: result.value.debug_metadata,
    };
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const loadItems = async () => {
    loadingItems.value = true;
    itemError.value = '';
    result.value = null;
    currentFile.value = null;

    const params = new URLSearchParams({ section: section.value });
    if (search.value.trim()) params.set('search', search.value.trim());
    if (isModuleSection.value && moduleId.value) params.set('module_id', moduleId.value);
    if (isModuleSection.value && activityType.value) params.set('activity_type', activityType.value);

    try {
        const response = await fetch(`${props.routes.items}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();

        if (!response.ok) throw new Error(payload.message ?? 'Could not load True Sandbox items.');

        items.value = payload.items ?? [];
        selectedItemId.value = items.value[0]?.id ?? '';
        recorderResetKey.value += 1;
    } catch (loadError) {
        itemError.value = loadError.message ?? 'Could not load True Sandbox items.';
        items.value = [];
        selectedItemId.value = '';
    } finally {
        loadingItems.value = false;
    }
};

watch(section, () => {
    moduleId.value = '';
    activityType.value = '';
    loadItems();
});

watch(selectedItemId, () => {
    currentFile.value = null;
    result.value = null;
    error.value = '';
    recorderResetKey.value += 1;
});

const rememberAudio = (file) => {
    currentFile.value = file;
    result.value = null;
    error.value = '';
};

const clearAudio = () => {
    currentFile.value = null;
    result.value = null;
    error.value = '';
};

const appendItemPayload = (payload, item) => {
    payload.append('section', section.value);
    payload.append('item_id', item.id);
    payload.append('item_source', item.source ?? '');
    payload.append('expected_text', item.expected_text ?? '');
    payload.append('prompt_text', item.prompt ?? '');
    payload.append('prompt_type', item.prompt_type ?? 'word');
    payload.append('task_type', item.task_type ?? '');
    payload.append('activity_type', item.activity_type ?? '');
    payload.append('assessment_type', item.assessment_type ?? 'true_sandbox');
    payload.append('module_key', item.module?.key ?? '');
    (item.accepted_answers ?? []).forEach((answer) => payload.append('accepted_answers[]', answer));
};

const runAsr = async (file = currentFile.value) => {
    const item = selectedItem.value;
    if (!item || !file) {
        error.value = 'Choose an item and record audio first.';
        return;
    }

    submitting.value = true;
    error.value = '';
    result.value = null;

    try {
        const payload = new FormData();
        payload.append('audio', file);
        if (file.durationSeconds != null) {
            payload.append('duration_seconds', String(file.durationSeconds));
        }
        appendAudioMetadata(payload, file);
        appendItemPayload(payload, item);

        const response = await fetch(props.routes.analyze, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: payload,
        });
        const payloadResult = await response.json();

        if (!response.ok) {
            throw new Error(payloadResult.message ?? 'The True Sandbox ASR request failed.');
        }

        result.value = payloadResult;
    } catch (submitError) {
        error.value = submitError.message ?? 'The True Sandbox ASR request failed.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AdminLayout>
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-black">True Sandbox</h1>
                <p class="max-w-3xl text-sm font-bold text-muted">
                    Admin-only ASR testing with no learner, attempt, prerequisite, unlock, or progression state. The existing Testing / QA Mode remains unchanged for learner-flow testing.
                </p>
            </div>
            <Link href="/admin/testing" class="rounded-xl bg-primary-light px-4 py-2 text-sm font-black text-primary">Back to QA Mode</Link>
        </div>

        <div class="grid gap-5 xl:grid-cols-[380px_1fr]">
            <div class="space-y-5">
                <DashboardCard>
                    <div class="mb-4 flex items-center gap-2">
                        <FileSearch class="size-5 text-primary" />
                        <h2 class="text-lg font-black">Load Any ASR Activity</h2>
                    </div>

                    <label class="grid gap-1 text-sm font-black text-muted">
                        ASR section
                        <select v-model="section" class="rounded-xl border border-border px-3 py-2 text-text">
                            <option v-for="item in sections" :key="item.key" :value="item.key">{{ item.label }}</option>
                        </select>
                    </label>
                    <p class="mt-2 text-xs font-bold text-muted">{{ selectedSection?.description }}</p>

                    <div v-if="isModuleSection" class="mt-4 grid gap-3">
                        <label class="grid gap-1 text-sm font-black text-muted">
                            Module
                            <select v-model="moduleId" class="rounded-xl border border-border px-3 py-2 text-text" @change="loadItems">
                                <option value="">All modules</option>
                                <option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-black text-muted">
                            Activity type
                            <select v-model="activityType" class="rounded-xl border border-border px-3 py-2 text-text" @change="loadItems">
                                <option value="">All activity types</option>
                                <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </label>
                    </div>

                    <form class="mt-4 flex gap-2" @submit.prevent="loadItems">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-2.5 size-4 text-muted" />
                            <input v-model="search" class="w-full rounded-xl border border-border py-2 pl-9 pr-3 text-sm font-bold" placeholder="Search item text">
                        </div>
                        <button class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-black text-white" :disabled="loadingItems">
                            <RefreshCcw class="size-4" />
                            Load
                        </button>
                    </form>

                    <p v-if="itemError" class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm font-black text-red-700">{{ itemError }}</p>

                    <label class="mt-4 grid gap-1 text-sm font-black text-muted">
                        Test item
                        <select v-model="selectedItemId" class="min-h-12 rounded-xl border border-border px-3 py-2 text-text">
                            <option v-for="item in items" :key="item.id" :value="item.id">
                                {{ item.title }} - {{ item.expected_text }}
                            </option>
                        </select>
                    </label>
                    <p class="mt-2 text-xs font-bold text-muted">{{ items.length }} item{{ items.length === 1 ? '' : 's' }} loaded.</p>
                </DashboardCard>

                <DashboardCard v-if="selectedItem">
                    <h2 class="text-lg font-black">Selected Prompt</h2>
                    <div class="mt-3 grid gap-3 text-sm">
                        <div>
                            <p class="text-xs font-black uppercase text-muted">Expected text</p>
                            <p class="rounded-xl bg-primary-light px-3 py-2 font-black text-primary">{{ expectedText }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-black uppercase text-muted">Prompt</p>
                            <p class="rounded-xl bg-slate-50 px-3 py-2 font-bold text-text">{{ selectedItem.prompt || expectedText }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs font-black text-muted">
                            <span class="rounded-xl bg-slate-50 px-3 py-2">Prompt: {{ selectedItem.prompt_type }}</span>
                            <span class="rounded-xl bg-slate-50 px-3 py-2">Task: {{ selectedItem.task_type }}</span>
                            <span v-if="selectedItem.module" class="rounded-xl bg-slate-50 px-3 py-2">Module: {{ selectedItem.module.title }}</span>
                            <span class="rounded-xl bg-slate-50 px-3 py-2">Source: {{ selectedItem.source }}</span>
                        </div>
                    </div>
                </DashboardCard>

                <DashboardCard v-if="selectedItem">
                    <AudioRecorder
                        :key="`${selectedItem.id}-${recorderResetKey}`"
                        :reset-key="`${selectedItem.id}-${recorderResetKey}`"
                        :max-duration-seconds="recorderPromptType === 'passage' ? 90 : 30"
                        :min-duration-seconds="recorderPromptType === 'passage' ? 1 : 0.5"
                        :prompt-type="recorderPromptType"
                        :require-review-before-submit="true"
                        :submitting="submitting"
                        :submitted="Boolean(result?.ok)"
                        label="True Sandbox voice"
                        submit-label="Run ASR"
                        @recorded="rememberAudio"
                        @submit="runAsr"
                        @cleared="clearAudio"
                    />
                    <button
                        type="button"
                        class="mt-3 w-full rounded-xl border-2 border-border px-4 py-2 text-sm font-black text-primaryDark disabled:opacity-50"
                        :disabled="!currentFile || submitting"
                        @click="runAsr()"
                    >
                        Run ASR Again
                    </button>
                </DashboardCard>
            </div>

            <div class="space-y-5">
                <DashboardCard>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Activity class="size-5 text-primary" />
                            <h2 class="text-lg font-black">ASR Result</h2>
                        </div>
                        <span v-if="result" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-black" :class="result.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                            <CheckCircle2 v-if="result.ok" class="size-4" />
                            <XCircle v-else class="size-4" />
                            {{ result.ok ? 'Returned' : 'Failed' }}
                        </span>
                    </div>

                    <div v-if="submitting" class="mt-8 rounded-2xl bg-primary-light p-8 text-center font-black text-primary">Running ASR...</div>
                    <div v-else-if="error" class="mt-4 flex gap-3 rounded-2xl bg-red-50 p-4 text-sm font-black text-red-700">
                        <AlertTriangle class="size-5 shrink-0" />
                        <span>{{ error }}</span>
                    </div>
                    <div v-else-if="!result" class="mt-8 rounded-2xl border border-dashed border-border p-10 text-center font-bold text-muted">
                        Record audio and click Run ASR to inspect transcripts, correction layers, and scoring.
                    </div>

                    <div v-else class="mt-4 grid gap-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl border border-border p-4">
                                <p class="text-xs font-black uppercase text-muted">Raw transcript</p>
                                <p class="mt-2 text-xl font-black text-text">{{ result.raw_transcript || 'Not reported' }}</p>
                            </div>
                            <div class="rounded-2xl border border-border p-4">
                                <p class="text-xs font-black uppercase text-muted">Corrected transcript</p>
                                <p class="mt-2 text-xl font-black text-text">{{ result.corrected_transcript || result.scoring_transcript || 'Not reported' }}</p>
                            </div>
                            <div class="rounded-2xl border border-border p-4">
                                <p class="text-xs font-black uppercase text-muted">Displayed transcript</p>
                                <p class="mt-2 text-xl font-black text-text">{{ result.displayed_transcript || normalizedResult?.displayTranscript || 'Not reported' }}</p>
                            </div>
                            <div class="rounded-2xl border border-border p-4">
                                <p class="text-xs font-black uppercase text-muted">Decision</p>
                                <p class="mt-2 text-xl font-black" :class="result.scoring?.accepted ? 'text-green-700' : 'text-red-700'">
                                    {{ result.scoring?.accepted === true ? 'Accepted' : result.retry_required ? 'Retry required' : 'Not accepted' }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-3">
                            <div><p class="text-xs font-black text-muted">Strategy</p><p class="font-black text-text">{{ result.correction_strategy_used || result.dynamic_correction_strategy || result.asr_spelling_variant_strategy || 'Not reported' }}</p></div>
                            <div><p class="text-xs font-black text-muted">Reason</p><p class="font-black text-text">{{ result.dynamic_correction_reason || result.variant_reason || result.scoring?.correction_reason || 'Not reported' }}</p></div>
                            <div><p class="text-xs font-black text-muted">Retry / uncertain</p><p class="font-black text-text">{{ result.retry_required ? 'Retry required' : 'No retry' }} / {{ result.uncertain ? 'Uncertain' : 'Certain enough' }}</p></div>
                            <div><p class="text-xs font-black text-muted">Phonetic similarity</p><p class="font-black text-text">{{ result.phonetic_similarity_score ?? result.dynamic_phoneme_similarity ?? 'Not reported' }}</p></div>
                            <div><p class="text-xs font-black text-muted">GOP</p><p class="font-black text-text">{{ result.gop_score ?? 'Not reported' }} <span v-if="result.gop_decision">({{ result.gop_decision }})</span></p></div>
                            <div><p class="text-xs font-black text-muted">Word accuracy</p><p class="font-black text-text">{{ result.scoring?.word_accuracy ?? 'Not reported' }}</p></div>
                        </div>

                        <div v-if="result.word_alignment?.length" class="overflow-auto rounded-2xl border border-border">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-50 text-xs font-black uppercase text-muted">
                                    <tr>
                                        <th class="px-3 py-2">Expected</th>
                                        <th class="px-3 py-2">Recognized</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Correct</th>
                                        <th class="px-3 py-2">Confidence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(word, index) in result.word_alignment" :key="index" class="border-t">
                                        <td class="px-3 py-2 font-bold">{{ word.expected_word ?? word.expected_chunk ?? '' }}</td>
                                        <td class="px-3 py-2 font-bold">{{ word.recognized_word ?? word.recognized_chunk ?? '' }}</td>
                                        <td class="px-3 py-2 font-black">{{ word.status ?? word.operation ?? '' }}</td>
                                        <td class="px-3 py-2 font-black" :class="word.counts_as_correct ? 'text-green-700' : 'text-red-700'">{{ word.counts_as_correct ? 'Yes' : 'No' }}</td>
                                        <td class="px-3 py-2 font-bold">{{ word.alignment_confidence ?? word.dynamic_correction_confidence ?? '' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </DashboardCard>

                <AdminDebugPanel v-if="compactDebug" title="Correction Layer Debug" :data="compactDebug" />
                <AdminDebugPanel v-if="result" title="Full True Sandbox Response" :data="result" />
            </div>
        </div>
    </AdminLayout>
</template>
