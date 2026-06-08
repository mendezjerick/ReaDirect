<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';
import { getPassageImage } from '../../utils/readingIllustrations';

const props = defineProps({
    passage: Object,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});

const savedPassageResponse = props.passage?.saved_response ?? {};
const form = useForm({
    assessment_attempt_id: props.assessmentAttemptId,
    incorrect_words: 0,
    audio: null,
    audio_file_id: savedPassageResponse.audio_file_id ?? null,
    duration_seconds: null,
});
const audioFile = ref(null);
const transcript = ref(String(savedPassageResponse.displayed_transcript ?? savedPassageResponse.answer ?? '').trim());
const wordAlignment = ref(Array.isArray(savedPassageResponse.word_alignment) ? savedPassageResponse.word_alignment : []);
const uploadError = ref('');
const uploading = ref(false);
const agentState = computed(() => uploading.value ? 'thinking' : (uploadError.value ? 'retry' : 'listening'));
const agentMessage = computed(() => uploadError.value || (uploading.value
    ? 'Checking your reading.'
    : 'Read the passage aloud. Try your best and speak clearly.'));
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);
const passageImage = computed(() => getPassageImage(props.passage?.source_csv_id));

const canonicalGroups = [
    ['small', 'little'],
    ['big', 'large'],
    ['mom', 'mother', 'mama'],
    ['dad', 'father', 'papa'],
    ['job', 'work'],
    ['kid', 'child'],
    ['kids', 'children'],
    ['see', 'saw', 'seen'],
    ['run', 'runs', 'ran', 'running'],
    ['hop', 'hops', 'hopped', 'hopping'],
    ['wash', 'washes', 'washed', 'washing'],
    ['count', 'counts', 'counted', 'counting'],
    ['eat', 'eats', 'ate', 'eating'],
    ['find', 'finds', 'found', 'finding'],
    ['say', 'says', 'said', 'saying'],
    ['fill', 'fills', 'filled', 'filling'],
    ['feed', 'feeds', 'fed', 'feeding'],
    ['hen', 'hens'],
    ['egg', 'eggs'],
    ['hand', 'hands'],
    ['bee', 'be'],
    ['sea', 'see'],
    ['two', 'too', 'to'],
    ['one', 'won'],
];

const normalizeWord = (word) => word.toLowerCase().replace(/[^a-z0-9]/gi, '');
const canonicalWord = (word) => {
    const normalized = normalizeWord(word);

    for (const group of canonicalGroups) {
        if (group.includes(normalized)) {
            return group[0];
        }
    }

    return normalized;
};
const wordTokens = (text) => text.match(/\S+|\s+/g) ?? [];
const compactWords = (text) => wordTokens(text)
    .filter((token) => !/^\s+$/.test(token))
    .map((token, index) => ({ index, raw: token, normalized: normalizeWord(token) }))
    .filter((token) => token.normalized !== '');

const acceptedAlignmentStatuses = new Set([
    'correct',
    'exact_correct',
    'accepted_by_dynamic_expected_word_correction',
    'accepted_by_homophone',
    'accepted_by_phoneme_similarity',
    'accepted_by_gop',
    'accepted_by_asr_spelling_variant',
    'accepted_by_split_merge',
]);
const buildDiffFromAlignment = (expectedText, actualText, alignment) => {
    const expectedWords = compactWords(expectedText);
    const actualWords = compactWords(actualText);
    const expectedEntries = Array.isArray(alignment)
        ? alignment.filter((item) => item?.expected_word !== null && item?.expected_word !== undefined)
        : [];

    if (expectedEntries.length !== expectedWords.length) {
        return null;
    }

    const expectedStatus = expectedEntries.map((item) => {
        if (acceptedAlignmentStatuses.has(item?.status)) return 'correct';
        if (item?.status === 'partial') return 'semantic';
        if (item?.status === 'missing') return 'missing';
        return 'incorrect';
    });
    const incorrectCount = expectedStatus.filter((status) => status === 'incorrect' || status === 'missing' || status === 'semantic').length;

    return {
        incorrectCount,
        semanticCount: expectedStatus.filter((status) => status === 'semantic').length,
        expectedWords,
        expectedStatus,
        actualWords,
        actualStatus: Array(actualWords.length).fill('correct'),
    };
};
const buildDiff = (expectedText, actualText, alignment = []) => {
    const aligned = buildDiffFromAlignment(expectedText, actualText, alignment);
    if (aligned) return aligned;

    const expectedWords = compactWords(expectedText);
    const actualWords = compactWords(actualText);
    const costs = Array.from({ length: expectedWords.length + 1 }, () => Array(actualWords.length + 1).fill(0));
    const ops = Array.from({ length: expectedWords.length + 1 }, () => Array(actualWords.length + 1).fill(null));

    for (let i = 0; i <= expectedWords.length; i += 1) {
        costs[i][0] = i;
        if (i > 0) ops[i][0] = 'delete';
    }

    for (let j = 0; j <= actualWords.length; j += 1) {
        costs[0][j] = j;
        if (j > 0) ops[0][j] = 'insert';
    }

    for (let i = 1; i <= expectedWords.length; i += 1) {
        for (let j = 1; j <= actualWords.length; j += 1) {
            const semanticMatch = canonicalWord(expectedWords[i - 1].raw) === canonicalWord(actualWords[j - 1].raw);
            const exactMatch = expectedWords[i - 1].normalized === actualWords[j - 1].normalized;
            const replaceCost = exactMatch || semanticMatch ? 0 : 1;
            const candidates = [
                { cost: costs[i - 1][j] + 1, op: 'delete' },
                { cost: costs[i][j - 1] + 1, op: 'insert' },
                { cost: costs[i - 1][j - 1] + replaceCost, op: exactMatch ? 'match' : (semanticMatch ? 'semantic' : 'replace') },
            ].sort((a, b) => a.cost - b.cost);

            costs[i][j] = candidates[0].cost;
            ops[i][j] = candidates[0].op;
        }
    }

    const expectedStatus = Array(expectedWords.length).fill('correct');
    const actualStatus = Array(actualWords.length).fill('correct');
    let i = expectedWords.length;
    let j = actualWords.length;

    while (i > 0 || j > 0) {
        const op = ops[i][j];

        if (op === 'match') {
            i -= 1;
            j -= 1;
            continue;
        }

        if (op === 'semantic') {
            expectedStatus[i - 1] = 'semantic';
            actualStatus[j - 1] = 'semantic';
            i -= 1;
            j -= 1;
            continue;
        }

        if (op === 'replace') {
            expectedStatus[i - 1] = 'incorrect';
            actualStatus[j - 1] = 'incorrect';
            i -= 1;
            j -= 1;
            continue;
        }

        if (op === 'delete') {
            expectedStatus[i - 1] = 'missing';
            i -= 1;
            continue;
        }

        if (op === 'insert') {
            actualStatus[j - 1] = 'extra';
            j -= 1;
            continue;
        }

        break;
    }

    return {
        incorrectCount: costs[expectedWords.length][actualWords.length],
        semanticCount: expectedStatus.filter((status) => status === 'semantic').length,
        expectedWords,
        expectedStatus,
        actualWords,
        actualStatus,
    };
};

const diff = computed(() => buildDiff(props.passage?.prompt ?? '', transcript.value, wordAlignment.value));
if (transcript.value.trim() !== '') {
    form.incorrect_words = diff.value.incorrectCount;
}
const hasReadingResult = computed(() => transcript.value.trim() !== '' || wordAlignment.value.length > 0);
const highlightedPassageTokens = computed(() => {
    const tokens = wordTokens(props.passage?.prompt ?? '');

    if (!hasReadingResult.value) {
        return tokens.map((token) => ({ text: token, status: 'neutral' }));
    }

    const statuses = diff.value.expectedStatus;
    let wordIndex = 0;

    return tokens.map((token) => {
        if (/^\s+$/.test(token) || normalizeWord(token) === '') {
            return { text: token, status: 'neutral' };
        }

        const status = statuses[wordIndex] ?? 'neutral';
        wordIndex += 1;

        return { text: token, status };
    });
});

const canSubmit = computed(() => {
    const hasIncorrectWords = form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
    const hasRecording = Boolean(form.audio_file_id) || Boolean(form.audio);

    if (canUseManualFallback.value) {
        return !uploading.value && hasIncorrectWords;
    }

    return !uploading.value && hasRecording && transcript.value.trim() !== '';
});

const uploadTranscript = async (file) => {
    uploading.value = true;
    uploadError.value = '';

    try {
        const payload = new FormData();
        payload.append('audio', file);
        payload.append('context_type', 'passage_reading');
        payload.append('assessment_attempt_id', String(props.assessmentAttemptId));
        if (form.duration_seconds != null) {
            payload.append('duration_seconds', String(form.duration_seconds));
        }
        appendAudioMetadata(payload, file);

        const response = await fetch('/learner/audio/upload', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: payload,
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message ?? 'We had trouble checking your answer. Please try again.');
        }

        const asr = normalizeAsrResponse(result);

        if (asr.canSubmit) {
            form.audio_file_id = result.audio_file_id ?? null;
            form.audio = null;
            transcript.value = asr.displayTranscript;
            wordAlignment.value = asr.wordAlignment;
            form.incorrect_words = diff.value.incorrectCount;
            return;
        }

        form.audio_file_id = null;
        transcript.value = '';
        wordAlignment.value = [];
        uploadError.value = asr.message;
    } catch (error) {
        uploadError.value = error.message || 'We had trouble checking your reading. Please try again.';
    } finally {
        uploading.value = false;
    }
};

const rememberAudio = (file) => {
    audioFile.value = file;
    form.audio = file;
    form.audio_file_id = null;
    form.duration_seconds = file.durationSeconds ?? null;
    transcript.value = '';
    wordAlignment.value = [];
    uploadError.value = '';
};

const clearAudio = () => {
    audioFile.value = null;
    form.audio = null;
    form.audio_file_id = null;
    form.duration_seconds = null;
    transcript.value = '';
    wordAlignment.value = [];
    uploadError.value = '';
};

const submit = () => {
    if (canUseManualFallback.value && !form.audio_file_id) {
        form.audio = null;
        form.duration_seconds = null;
    }

    form.post('/learner/diagnostic/passage', { forceFormData: true });
};
</script>

<template>
    <LearnerLayout :progress="78" diagnostic-step="sentence-reading">
        <template #agent>
            <AgentSpeakerPanel
                compact
                agent-type="assessment"
                :state="agentState"
                :message="agentMessage"
            />
        </template>

        <div class="mx-auto grid max-w-2xl gap-4 px-0.5">
            <!-- Header badges & progress -->
            <div class="anim-fade-down grid gap-3 px-1">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-emerald-50 px-3.5 py-1.5 text-[13px] font-black text-emerald-600 ring-1 ring-emerald-200/60">
                        📖 50 words
                    </span>
                    <span
                        :class="uploading
                            ? 'rounded-full bg-amber-50 px-3.5 py-1.5 text-[13px] font-black text-amber-600 ring-1 ring-amber-200/60'
                            : 'rounded-full bg-primary/5 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10'"
                    >
                        {{ uploading ? '⏳ Checking' : '⏱️ Max 60 seconds' }}
                    </span>
                </div>
                <div class="h-3.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500 ease-out" style="width: 78%" />
                </div>
            </div>

            <!-- Passage text card -->
            <section
                class="anim-card relative rounded-[36px] border-[3px] border-primary/10 bg-white p-6 shadow-2xl shadow-primary/10"
            >
                <!-- Decorative blur blobs -->
                <div class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
                <div class="pointer-events-none absolute -bottom-10 -right-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" aria-hidden="true" />

                <!-- Passage illustration -->
                <div v-if="passageImage" class="anim-pop relative mb-4 flex justify-center">
                    <img :src="passageImage" :alt="passage.title" class="h-[200px] w-full rounded-[24px] object-cover drop-shadow-lg sm:h-[260px] lg:h-[320px]">
                </div>

                <p class="relative text-2xl font-black leading-relaxed text-slate-800 lg:text-[28px]">
                    <template v-for="(token, index) in highlightedPassageTokens" :key="index">
                        <span
                            :class="{
                                'rounded-lg bg-rose-50 px-1 text-rose-600 ring-1 ring-rose-200/60': token.status === 'incorrect' || token.status === 'missing',
                                'rounded-lg bg-amber-50 px-1 text-amber-700 ring-1 ring-amber-200/60': token.status === 'semantic',
                            }"
                        >{{ token.text }}</span>
                    </template>
                </p>
            </section>

            <!-- Recording & transcript panel -->
            <div class="anim-slide-up grid gap-4 rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-4 shadow-sm lg:grid-cols-[220px_1fr]">
                <!-- Recorder header -->
                <div class="flex flex-col gap-3">
                    <div class="mb-2 flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20">
                            <!-- Mic icon inline SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="11" rx="3" /><path d="M5 10a7 7 0 0 0 14 0" /><line x1="8" y1="21" x2="16" y2="21" /><line x1="12" y1="17" x2="12" y2="21" /></svg>
                        </span>
                        <div>
                            <p class="text-[16px] font-black text-slate-800">Passage voice</p>
                            <p class="text-[12px] font-semibold leading-snug text-slate-400">Read aloud clearly</p>
                        </div>
                    </div>
                    <AudioRecorder
                        compact
                        :max-duration-seconds="60"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="uploading"
                        :submitted="Boolean(form.audio_file_id) && !uploadError"
                        label="Passage voice"
                        prompt-type="passage"
                        @recorded="rememberAudio"
                        @submit="uploadTranscript"
                        @cleared="clearAudio"
                    />
                </div>

                <!-- Transcript section -->
                <div class="grid gap-3">
                    <label class="grid gap-3 text-[16px] font-black text-slate-800">
                        <span class="inline-flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-md shadow-violet-500/20">
                                <!-- MessageCircle icon inline SVG -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" /></svg>
                            </span>
                            You said
                        </span>
                        <div class="min-h-32 rounded-[20px] border-2 border-slate-200/80 bg-white p-5 text-xl font-black text-slate-800 transition-all">
                            <span v-if="transcript">
                                <template v-for="(word, index) in diff.actualWords" :key="`${word.index}-${index}`">
                                    <span
                                        class="mr-2 inline-block rounded-lg px-1"
                                        :class="{
                                            'bg-rose-50 text-rose-600 ring-1 ring-rose-200/60': diff.actualStatus[index] === 'incorrect' || diff.actualStatus[index] === 'extra',
                                            'bg-amber-50 text-amber-700 ring-1 ring-amber-200/60': diff.actualStatus[index] === 'semantic',
                                        }"
                                    >{{ word.raw }}</span>
                                </template>
                            </span>
                            <span v-else class="text-[15px] font-semibold text-slate-400">
                                {{ uploading ? 'Checking your recording...' : 'Your words will appear here' }}
                            </span>
                        </div>
                    </label>

                    <!-- Manual fallback input -->
                    <label v-if="canUseManualFallback" class="grid content-center gap-2 text-[16px] font-black text-slate-800">
                        Developer QA: Incorrect Words Override
                        <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-[20px] border-2 border-slate-200/80 bg-white px-4 py-3 text-lg font-black text-slate-800 transition-all focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                    </label>

                    <!-- Semantic feedback -->
                    <p v-if="diff.semanticCount > 0" class="rounded-[20px] bg-amber-50 px-4 py-3 text-[14px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                        {{ diff.semanticCount }} meaning-preserving word {{ diff.semanticCount === 1 ? 'swap was' : 'swaps were' }} understood and not counted as a full mismatch.
                    </p>

                    <!-- Upload error -->
                    <p v-if="uploadError" class="rounded-[20px] bg-rose-50 px-4 py-3 text-[14px] font-semibold text-rose-600 ring-1 ring-rose-200/60">
                        {{ uploadError }}
                    </p>
                </div>
            </div>
        </div>

        <BottomActionBar>
            <PrimaryButton :disabled="form.processing || !canSubmit" @click="submit">Continue</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* Card spring entrance */
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Content pop (for large text/letters) */
.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}

/* Header fade down */
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Panel slide up */
.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Staggered children */
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

