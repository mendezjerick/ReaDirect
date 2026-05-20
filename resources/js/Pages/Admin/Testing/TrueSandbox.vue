<script setup>
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Activity, AlertTriangle, ArrowLeft, CheckCircle2, FileSearch, Loader2, RefreshCcw, Search, XCircle } from 'lucide-vue-next';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
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
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">True Sandbox</h1>
                <p class="mt-1 max-w-3xl text-sm font-medium text-muted">
                    Admin-only ASR testing with no learner, attempt, prerequisite, unlock, or progression state.
                    <span class="block mt-0.5">Testing / QA Mode remains unchanged for learner-flow testing.</span>
                </p>
            </div>
            <Link href="/admin/testing" class="group inline-flex shrink-0 w-full md:w-auto items-center justify-center gap-2 rounded-xl bg-background px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:shadow-sm">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to QA Mode
            </Link>
        </div>

        <div class="grid gap-6 lg:grid-cols-[400px_1fr] xl:grid-cols-[450px_1fr]">
            <!-- ── Left column ────────────────────────────────── -->
            <div class="space-y-5">
                <!-- Load Any ASR Activity -->
                <DashboardCard class="card-in">
                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                            <FileSearch class="size-4" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Load Any ASR Activity</h2>
                    </div>

                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">ASR section</span>
                        <select v-model="section" class="w-full rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-text bg-white transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option v-for="item in sections" :key="item.key" :value="item.key">{{ item.label }}</option>
                        </select>
                    </label>
                    <p class="mt-2 text-[11px] font-medium text-muted">{{ selectedSection?.description }}</p>

                    <div v-if="isModuleSection" class="mt-4 grid gap-3">
                        <label class="grid gap-1.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Module</span>
                            <select v-model="moduleId" class="w-full rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-text bg-white transition-all duration-200 hover:border-primary/40" @change="loadItems">
                                <option value="">All modules</option>
                                <option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option>
                            </select>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Activity type</span>
                            <select v-model="activityType" class="w-full rounded-xl border border-border px-3 py-2.5 text-sm font-semibold text-text bg-white transition-all duration-200 hover:border-primary/40" @change="loadItems">
                                <option value="">All activity types</option>
                                <option v-for="type in activityTypes" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </label>
                    </div>

                    <form class="mt-4 flex flex-col sm:flex-row gap-2" @submit.prevent="loadItems">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-3 size-4 text-muted transition-colors" />
                            <input v-model="search" class="w-full rounded-xl border border-border py-2.5 pl-9 pr-3 text-[13px] font-medium bg-white transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search item text">
                        </div>
                        <button class="w-full sm:w-auto inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="loadingItems">
                            <Loader2 v-if="loadingItems" class="size-4 animate-spin" />
                            <RefreshCcw v-else class="size-4 transition-transform duration-200 group-hover:rotate-45" />
                            Load
                        </button>
                    </form>

                    <Transition name="flash">
                        <p v-if="itemError" class="mt-3 flex items-center gap-2 rounded-xl bg-red-50 border border-red-200/60 px-3 py-2.5 text-sm font-semibold text-red-700">
                            <AlertTriangle class="size-4 shrink-0" />
                            {{ itemError }}
                        </p>
                    </Transition>

                    <div class="mt-4 grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Test item ({{ items.length }} loaded)</span>
                        <div class="flex flex-col max-h-64 sm:max-h-80 overflow-y-auto rounded-xl border border-border/60 bg-white shadow-inner">
                            <!-- Loading skeleton for items -->
                            <template v-if="loadingItems">
                                <div v-for="i in 8" :key="'skel-'+i" class="px-3 py-3 border-b border-border/30 last:border-b-0">
                                    <div class="flex items-center gap-2">
                                        <div class="h-3.5 rounded-full ts-shimmer" :style="{ width: `${40 + (i * 7) % 50}%`, animationDelay: `${i * 80}ms` }"></div>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <button
                                    v-for="item in items"
                                    :key="item.id"
                                    type="button"
                                    @click="selectedItemId = item.id"
                                    class="w-full text-left px-3 py-2.5 text-sm border-b border-border/40 last:border-b-0 transition-all duration-150 focus:outline-none"
                                    :class="selectedItemId === item.id ? 'bg-primary/6 text-primary font-bold border-l-[3px] border-l-primary' : 'text-text font-semibold hover:bg-slate-50 focus:bg-slate-50'"
                                >
                                    <span class="block truncate max-w-full">{{ item.title }} - {{ item.expected_text }}</span>
                                </button>
                                <div v-if="items.length === 0" class="flex flex-col items-center gap-2 px-3 py-8 text-center text-muted">
                                    <FileSearch class="size-6 text-slate-300" />
                                    <p class="text-sm font-medium">No items found.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </DashboardCard>

                <!-- Selected Prompt -->
                <Transition name="ts-card-slide">
                    <DashboardCard v-if="selectedItem" class="card-in" style="animation-delay: 80ms">
                        <div class="mb-4 flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                                <Search class="size-4" />
                            </div>
                            <h2 class="text-sm font-bold text-text">Selected Prompt</h2>
                        </div>
                        <div class="grid gap-3 text-sm">
                            <div class="flex flex-col">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Expected text</p>
                                <div class="rounded-xl bg-primary/5 border border-primary/10 px-3.5 py-2.5 transition-all duration-200">
                                    <p class="font-bold text-primary break-words whitespace-pre-wrap">{{ expectedText }}</p>
                                </div>
                            </div>
                            <div class="flex flex-col">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Prompt</p>
                                <div class="rounded-xl bg-background border border-border/60 px-3.5 py-2.5 max-h-40 overflow-y-auto">
                                    <p class="font-semibold text-text break-words whitespace-pre-wrap">{{ selectedItem.prompt || expectedText }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <span class="flex items-center rounded-lg bg-background px-3 py-2 text-[11px] font-bold text-muted truncate transition-colors duration-150 hover:bg-slate-100" :title="selectedItem.prompt_type">Prompt: {{ selectedItem.prompt_type }}</span>
                                <span class="flex items-center rounded-lg bg-background px-3 py-2 text-[11px] font-bold text-muted truncate transition-colors duration-150 hover:bg-slate-100" :title="selectedItem.task_type">Task: {{ selectedItem.task_type }}</span>
                                <span v-if="selectedItem.module" class="flex items-center rounded-lg bg-background px-3 py-2 text-[11px] font-bold text-muted truncate transition-colors duration-150 hover:bg-slate-100" :title="selectedItem.module.title">Module: {{ selectedItem.module.title }}</span>
                                <span class="flex items-center rounded-lg bg-background px-3 py-2 text-[11px] font-bold text-muted truncate transition-colors duration-150 hover:bg-slate-100" :title="selectedItem.source">Source: {{ selectedItem.source }}</span>
                            </div>
                        </div>
                    </DashboardCard>
                </Transition>

                <!-- Audio Recorder -->
                <Transition name="ts-card-slide">
                    <DashboardCard v-if="selectedItem" class="card-in" style="animation-delay: 160ms">
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
                            class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-background px-4 py-2.5 text-sm font-semibold text-slate-600 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97] disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!currentFile || submitting"
                            @click="runAsr()"
                        >
                            <Loader2 v-if="submitting" class="size-4 animate-spin" />
                            <RefreshCcw v-else class="size-4" />
                            Run ASR Again
                        </button>
                    </DashboardCard>
                </Transition>
            </div>

            <!-- ── Right column — ASR Results ─────────────────── -->
            <div class="space-y-5 min-w-0">
                <DashboardCard class="card-in" style="animation-delay: 100ms">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                                <Activity class="size-4" />
                            </div>
                            <h2 class="text-sm font-bold text-text">ASR Result</h2>
                        </div>
                        <Transition name="ts-badge-pop">
                            <span v-if="result" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold" :class="result.ok ? 'bg-green-50 text-green-700 ring-1 ring-green-200/60' : 'bg-red-50 text-red-700 ring-1 ring-red-200/60'">
                                <CheckCircle2 v-if="result.ok" class="size-3.5" />
                                <XCircle v-else class="size-3.5" />
                                {{ result.ok ? 'Returned' : 'Failed' }}
                            </span>
                        </Transition>
                    </div>

                    <!-- ASR content area with animated transitions -->
                    <Transition name="ts-fade" mode="out-in">
                        <!-- Loading: shimmer skeleton -->
                        <div v-if="submitting" key="asr-loading" class="mt-6 space-y-4">
                            <div class="flex items-center justify-center gap-3 rounded-2xl bg-primary/5 border border-primary/10 p-6">
                                <div class="relative">
                                    <div class="absolute inset-0 rounded-full bg-primary/20 animate-ping"></div>
                                    <Loader2 class="relative size-5 text-primary animate-spin" />
                                </div>
                                <span class="text-sm font-bold text-primary">Analyzing speech…</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div v-for="i in 4" :key="'ts-'+i" class="rounded-2xl border border-border/40 p-4">
                                    <div class="h-2.5 w-24 rounded-full ts-shimmer mb-3" :style="{ animationDelay: `${i * 120}ms` }"></div>
                                    <div class="space-y-2.5">
                                        <div class="h-4 rounded-full ts-shimmer" :style="{ width: '100%', animationDelay: `${i * 120 + 40}ms` }"></div>
                                        <div class="h-4 rounded-full ts-shimmer" :style="{ width: `${60 + (i * 8)}%`, animationDelay: `${i * 120 + 80}ms` }"></div>
                                        <div class="h-4 rounded-full ts-shimmer" :style="{ width: `${40 + (i * 12)}%`, animationDelay: `${i * 120 + 120}ms` }"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-background/60 border border-border/30 p-4">
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <div v-for="i in 6" :key="'met-'+i" class="rounded-xl bg-surface p-3 space-y-2">
                                        <div class="h-2.5 w-16 rounded-full ts-shimmer" :style="{ animationDelay: `${i * 100}ms` }"></div>
                                        <div class="h-3.5 rounded-full ts-shimmer" :style="{ width: `${55 + (i * 6)}%`, animationDelay: `${i * 100 + 50}ms` }"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Error state -->
                        <div v-else-if="error" key="asr-error" class="mt-4 flex items-start gap-3 rounded-2xl bg-red-50 border border-red-200/60 p-4 text-sm font-semibold text-red-700">
                            <AlertTriangle class="size-5 shrink-0 mt-0.5" />
                            <span>{{ error }}</span>
                        </div>

                        <!-- Empty state -->
                        <div v-else-if="!result" key="asr-empty" class="mt-8 flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border p-10 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 ring-4 ring-slate-100">
                                <Activity class="size-5 text-slate-300" />
                            </div>
                            <p class="text-sm font-medium text-muted max-w-sm">Record audio and click Run ASR to inspect transcripts, correction layers, and scoring.</p>
                        </div>

                        <!-- Results -->
                        <div v-else key="asr-results" class="mt-4 grid gap-4">
                            <!-- Transcript cards with colored left borders -->
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-border/60 border-l-[3px] border-l-slate-300 p-4 flex flex-col ts-result-card" style="--delay: 0ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-2">Raw transcript</p>
                                    <div class="max-h-40 overflow-y-auto">
                                        <p class="text-base font-bold text-text break-words whitespace-pre-wrap">{{ result.raw_transcript || 'Not reported' }}</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-border/60 border-l-[3px] border-l-blue-400 p-4 flex flex-col ts-result-card" style="--delay: 60ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-2">Corrected transcript</p>
                                    <div class="max-h-40 overflow-y-auto">
                                        <p class="text-base font-bold text-text break-words whitespace-pre-wrap">{{ result.corrected_transcript || result.scoring_transcript || 'Not reported' }}</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-border/60 border-l-[3px] border-l-violet-400 p-4 flex flex-col ts-result-card" style="--delay: 120ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-2">Displayed transcript</p>
                                    <div class="max-h-40 overflow-y-auto">
                                        <p class="text-base font-bold text-text break-words whitespace-pre-wrap">{{ result.displayed_transcript || normalizedResult?.displayTranscript || 'Not reported' }}</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-border/60 p-4 flex flex-col ts-result-card" style="--delay: 180ms"
                                     :class="{
                                         'border-l-[3px] border-l-green-400': result.scoring?.accepted === true,
                                         'border-l-[3px] border-l-amber-400': result.retry_required && !result.scoring?.accepted,
                                         'border-l-[3px] border-l-red-400': !result.scoring?.accepted && !result.retry_required,
                                         'border-l-[3px] border-l-slate-300': result.scoring?.accepted === undefined && !result.retry_required
                                     }">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-2">Decision</p>
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-bold text-sm"
                                              :class="{
                                                  'bg-green-50 text-green-700 ring-1 ring-green-200/60': result.scoring?.accepted === true,
                                                  'bg-amber-50 text-amber-700 ring-1 ring-amber-200/60': result.retry_required && !result.scoring?.accepted,
                                                  'bg-red-50 text-red-700 ring-1 ring-red-200/60': !result.scoring?.accepted && !result.retry_required,
                                                  'bg-slate-50 text-slate-700 ring-1 ring-slate-200/60': result.scoring?.accepted === undefined && !result.retry_required
                                              }">
                                            <CheckCircle2 v-if="result.scoring?.accepted === true" class="size-3.5" />
                                            <XCircle v-else-if="result.scoring?.accepted === false && !result.retry_required" class="size-3.5" />
                                            <AlertTriangle v-else-if="result.retry_required" class="size-3.5" />
                                            {{ result.scoring?.accepted === true ? 'Accepted' : result.retry_required ? 'Retry required / Uncertain' : result.scoring?.accepted === false ? 'Rejected' : 'Pending' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Metrics grid — Dashboard row pattern -->
                            <div class="grid gap-2 rounded-2xl bg-background/60 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="flex flex-col min-w-0 rounded-xl bg-surface px-3.5 py-2.5 transition-colors duration-150 hover:bg-blue-50/60 ts-result-card" style="--delay: 240ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Strategy</p>
                                    <p class="font-mono text-xs font-semibold text-text break-words whitespace-pre-wrap max-h-24 overflow-y-auto">{{ result.correction_strategy_used || result.dynamic_correction_strategy || result.asr_spelling_variant_strategy || 'Not reported' }}</p>
                                </div>
                                <div class="flex flex-col min-w-0 rounded-xl bg-surface px-3.5 py-2.5 transition-colors duration-150 hover:bg-blue-50/60 ts-result-card" style="--delay: 280ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Reason</p>
                                    <p class="font-mono text-xs font-semibold text-text break-words whitespace-pre-wrap max-h-24 overflow-y-auto">{{ result.dynamic_correction_reason || result.variant_reason || result.scoring?.correction_reason || 'Not reported' }}</p>
                                </div>
                                <div class="flex flex-col min-w-0 rounded-xl bg-surface px-3.5 py-2.5 transition-colors duration-150 hover:bg-blue-50/60 ts-result-card" style="--delay: 320ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Retry / uncertain</p>
                                    <p class="font-mono text-xs font-semibold text-text break-words whitespace-pre-wrap max-h-24 overflow-y-auto">{{ result.retry_required ? 'Retry required' : 'No retry' }} / {{ result.uncertain ? 'Uncertain' : 'Certain enough' }}</p>
                                </div>
                                <div class="flex flex-col min-w-0 rounded-xl bg-surface px-3.5 py-2.5 transition-colors duration-150 hover:bg-blue-50/60 ts-result-card" style="--delay: 360ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Phonetic similarity</p>
                                    <p class="font-mono text-xs font-semibold text-text break-words whitespace-pre-wrap max-h-24 overflow-y-auto">{{ result.phonetic_similarity_score ?? result.dynamic_phoneme_similarity ?? 'Not reported' }}</p>
                                </div>
                                <div class="flex flex-col min-w-0 rounded-xl bg-surface px-3.5 py-2.5 transition-colors duration-150 hover:bg-blue-50/60 ts-result-card" style="--delay: 400ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">GOP</p>
                                    <p class="font-mono text-xs font-semibold text-text break-words whitespace-pre-wrap max-h-24 overflow-y-auto">{{ result.gop_score ?? 'Not reported' }} <span v-if="result.gop_decision">({{ result.gop_decision }})</span></p>
                                </div>
                                <div class="flex flex-col min-w-0 rounded-xl bg-surface px-3.5 py-2.5 transition-colors duration-150 hover:bg-blue-50/60 ts-result-card" style="--delay: 440ms">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Word accuracy</p>
                                    <p class="font-mono text-xs font-semibold text-text break-words whitespace-pre-wrap max-h-24 overflow-y-auto">{{ result.scoring?.word_accuracy ?? 'Not reported' }}</p>
                                </div>
                            </div>

                            <!-- Word alignment table -->
                            <div v-if="result.word_alignment?.length" class="overflow-x-auto rounded-2xl border border-border/60 relative max-w-full ts-result-card" style="--delay: 500ms">
                                <table class="min-w-full text-left text-sm whitespace-nowrap">
                                    <thead class="bg-background text-[11px] font-bold uppercase tracking-wider text-muted sticky top-0 z-10 border-b border-border/60">
                                        <tr>
                                            <th class="px-4 py-3 border-r border-border/40">Expected</th>
                                            <th class="px-4 py-3 border-r border-border/40">Recognized</th>
                                            <th class="px-4 py-3 border-r border-border/40">Status</th>
                                            <th class="px-4 py-3 border-r border-border/40">Correct</th>
                                            <th class="px-4 py-3">Confidence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(word, index) in result.word_alignment" :key="index" class="border-b border-border/40 last:border-b-0 transition-colors duration-150 hover:bg-blue-50/40">
                                            <td class="px-4 py-2.5 font-semibold border-r border-border/40 truncate max-w-[200px]" :title="word.expected_word ?? word.expected_chunk ?? ''">{{ word.expected_word ?? word.expected_chunk ?? '' }}</td>
                                            <td class="px-4 py-2.5 font-semibold border-r border-border/40 truncate max-w-[200px]" :title="word.recognized_word ?? word.recognized_chunk ?? ''">{{ word.recognized_word ?? word.recognized_chunk ?? '' }}</td>
                                            <td class="px-4 py-2.5 border-r border-border/40">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold" :class="{
                                                    'bg-slate-100 text-slate-600': word.status === 'exact' || word.operation === 'exact',
                                                    'bg-amber-50 text-amber-700 ring-1 ring-amber-200/60': word.status === 'mismatch' || word.operation === 'substitution',
                                                    'bg-red-50 text-red-700 ring-1 ring-red-200/60': word.operation === 'deletion' || word.operation === 'insertion',
                                                    'bg-slate-50 text-text': !['exact', 'mismatch', 'substitution', 'deletion', 'insertion'].includes(word.status || word.operation)
                                                }">
                                                    {{ word.status ?? word.operation ?? '' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 border-r border-border/40">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold" :class="word.counts_as_correct ? 'bg-green-50 text-green-700 ring-1 ring-green-200/60' : 'bg-red-50 text-red-700 ring-1 ring-red-200/60'">
                                                    {{ word.counts_as_correct ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 font-medium text-muted">{{ word.alignment_confidence ?? word.dynamic_correction_confidence ?? '' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </Transition>
                </DashboardCard>

                <!-- Correction Layer Debug -->
                <Transition name="ts-card-slide">
                    <DashboardCard v-if="compactDebug">
                        <div class="mb-3 flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                                <AlertTriangle class="size-4" />
                            </div>
                            <h2 class="text-sm font-bold text-text">Correction Layer Debug</h2>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-border/60">
                            <pre class="max-h-96 overflow-y-auto bg-slate-950 p-4 text-xs font-mono leading-relaxed text-slate-100 whitespace-pre-wrap break-words">{{ JSON.stringify(compactDebug, null, 2) }}</pre>
                        </div>
                    </DashboardCard>
                </Transition>

                <!-- Full Response -->
                <Transition name="ts-card-slide">
                    <DashboardCard v-if="result">
                        <div class="mb-3 flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                                <Activity class="size-4" />
                            </div>
                            <h2 class="text-sm font-bold text-text">Full True Sandbox Response</h2>
                        </div>
                        <div class="overflow-hidden rounded-xl border border-border/60">
                            <pre class="max-h-96 overflow-y-auto bg-slate-950 p-4 text-xs font-mono leading-relaxed text-slate-100 whitespace-pre-wrap break-words">{{ JSON.stringify(result, null, 2) }}</pre>
                        </div>
                    </DashboardCard>
                </Transition>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* ─── Shimmer skeleton ────────────────────────────────── */
.ts-shimmer {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
    background-size: 400% 100%;
    animation: ts-shimmer 1.6s ease infinite;
    border-radius: 9999px;
}

@keyframes ts-shimmer {
    0%   { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* ─── Staggered result card entrance ──────────────────── */
.ts-result-card {
    animation: ts-result-slide-in 420ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--delay, 0ms);
}

@keyframes ts-result-slide-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ─── Card slide transition (Vue) ─────────────────────── */
.ts-card-slide-enter-active {
    transition: all 350ms cubic-bezier(0.16, 1, 0.3, 1);
}
.ts-card-slide-leave-active {
    transition: all 220ms ease;
}
.ts-card-slide-enter-from {
    opacity: 0;
    transform: translateY(14px);
}
.ts-card-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.98);
}

/* ─── Content fade transition (Vue) ───────────────────── */
.ts-fade-enter-active {
    transition: all 350ms cubic-bezier(0.16, 1, 0.3, 1);
}
.ts-fade-leave-active {
    transition: all 180ms ease;
}
.ts-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}
.ts-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

/* ─── Badge pop transition (Vue) ──────────────────────── */
.ts-badge-pop-enter-active {
    transition: all 400ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.ts-badge-pop-leave-active {
    transition: all 200ms ease;
}
.ts-badge-pop-enter-from {
    opacity: 0;
    transform: scale(0.7);
}
.ts-badge-pop-leave-to {
    opacity: 0;
    transform: scale(0.85);
}
</style>
