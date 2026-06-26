<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MessageSquareText } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from './AssessmentTaskWorkspace.vue';
import AudioRecorder from './AudioRecorder.vue';
import AsrTranscriptVisualizer from './AsrTranscriptVisualizer.vue';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';
import { getPassageImage } from '../../utils/readingIllustrations';

const props = defineProps({
    passage: { type: Object, required: true },
    assessmentAttemptId: { type: Number, required: true },
    assessmentMode: { type: Object, default: () => ({}) },
    submitUrl: { type: String, required: true },
    initialAgentMessage: { type: String, default: 'Read the passage aloud. Try your best and speak clearly.' },
    fallbackDiffForIncorrectWords: { type: Boolean, default: false },
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
const asrResult = ref(null);
const uploadError = ref('');
const uploading = ref(false);
const agentMessage = ref(props.initialAgentMessage);
const agentState = ref('listening');
const agentSpeaking = ref(false);
const passageChecked = ref(Boolean(transcript.value || form.audio_file_id));
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
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

const incorrectWordsFromAlignment = (alignment) => {
    if (!Array.isArray(alignment) || alignment.length === 0) return null;
    const expectedEntries = alignment.filter((item) => item?.expected_word !== null && item?.expected_word !== undefined);
    if (expectedEntries.length === 0) return null;

    return expectedEntries.filter((item) => !acceptedAlignmentStatuses.has(item?.status)).length;
};

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
const initialAlignedIncorrectWords = incorrectWordsFromAlignment(wordAlignment.value);
if (initialAlignedIncorrectWords !== null) {
    form.incorrect_words = initialAlignedIncorrectWords;
} else if (props.fallbackDiffForIncorrectWords && transcript.value.trim() !== '') {
    form.incorrect_words = diff.value.incorrectCount;
}

const hasIncorrectWords = () => form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
const highlightedPassageTokens = computed(() => {
    const tokens = wordTokens(props.passage?.prompt ?? '');

    if (!passageChecked.value) {
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
const canSubmitForReview = computed(() => {
    if (uploading.value) return false;
    if (audioFile.value || form.audio_file_id) return true;

    return canUseManualFallback.value && hasIncorrectWords();
});
const primaryLabel = computed(() => passageChecked.value ? 'Next' : 'Submit');
const primaryDisabled = computed(() => form.processing || uploading.value || (!passageChecked.value && !canSubmitForReview.value));

const applyIncorrectWordsFromCurrentResult = () => {
    const alignedIncorrectWords = incorrectWordsFromAlignment(wordAlignment.value);
    if (alignedIncorrectWords !== null) {
        form.incorrect_words = alignedIncorrectWords;
        return;
    }

    if (props.fallbackDiffForIncorrectWords) {
        form.incorrect_words = diff.value.incorrectCount;
    }
};

const uploadTranscript = async (file) => {
    if (uploading.value || form.processing) {
        return false;
    }

    uploading.value = true;
    uploadError.value = '';
    agentMessage.value = 'Checking your reading.';
    agentState.value = 'thinking';

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
        asrResult.value = result;

        if (!response.ok) {
            throw new Error(result.message ?? 'We had trouble checking your answer. Please try again.');
        }

        const asr = normalizeAsrResponse(result);

        if (asr.canSubmit) {
            form.audio_file_id = result.audio_file_id ?? null;
            form.audio = null;
            transcript.value = asr.displayTranscript;
            wordAlignment.value = asr.wordAlignment;
            applyIncorrectWordsFromCurrentResult();
            passageChecked.value = true;
            agentMessage.value = `You said: ${transcript.value}`;
            agentState.value = 'speaking';
            return true;
        }

        form.audio_file_id = null;
        transcript.value = '';
        wordAlignment.value = [];
        passageChecked.value = false;
        uploadError.value = asr.message;
        agentMessage.value = uploadError.value;
        agentState.value = 'retry';
        return false;
    } catch (error) {
        passageChecked.value = false;
        uploadError.value = error.message || 'We had trouble checking your reading. Please try again.';
        agentMessage.value = uploadError.value;
        agentState.value = 'retry';
        return false;
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
    asrResult.value = null;
    uploadError.value = '';
    passageChecked.value = false;
    agentMessage.value = 'Listen to your reading. If you are happy with it, click Submit.';
    agentState.value = 'speaking';
};

const clearAudio = () => {
    audioFile.value = null;
    form.audio = null;
    form.audio_file_id = null;
    form.duration_seconds = null;
    transcript.value = '';
    wordAlignment.value = [];
    asrResult.value = null;
    uploadError.value = '';
    passageChecked.value = false;
};

const submitCurrentForReview = async () => {
    if (uploading.value || form.processing) {
        return;
    }

    if (form.audio_file_id && transcript.value.trim()) {
        passageChecked.value = true;
        return;
    }

    if (audioFile.value) {
        await uploadTranscript(audioFile.value);
        return;
    }

    if (canUseManualFallback.value && hasIncorrectWords()) {
        form.audio = null;
        form.duration_seconds = null;
        passageChecked.value = true;
        uploadError.value = '';
        agentMessage.value = 'QA override is ready. Click Next to continue.';
        agentState.value = 'speaking';
        return;
    }

    agentMessage.value = 'Hold the orange button to record the passage first.';
    agentState.value = 'speaking';
};

const submit = () => {
    if (!passageChecked.value) {
        agentMessage.value = 'Click Submit first so I can check your reading.';
        agentState.value = 'speaking';
        return;
    }

    if (canUseManualFallback.value && !form.audio_file_id) {
        form.audio = null;
        form.duration_seconds = null;
    }

    form.post(props.submitUrl, {
        forceFormData: true,
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? 'We could not save this reading yet. Please try again.';
            agentMessage.value = Array.isArray(firstError) ? firstError[0] : firstError;
            agentState.value = 'retry';
        },
    });
};

const handlePrimary = () => {
    if (passageChecked.value) {
        submit();
        return;
    }

    submitCurrentForReview();
};

const setAgentSpeaking = (isSpeaking) => {
    agentSpeaking.value = isSpeaking === true;
};
</script>

<template>
    <LearnerLayout assessment-task>
        <AssessmentTaskWorkspace
            :agent-state="agentState"
            :agent-message="agentMessage"
            :progress="100"
            :primary-label="primaryLabel"
            :primary-disabled="primaryDisabled"
            :prompt-image="passageImage"
            @primary="handlePrimary"
            @agent-speaking-change="setAgentSpeaking"
        >
            <template #prompt>
                <div class="passage-prompt" aria-label="Reading passage">
                    <p v-if="passage?.title" class="passage-title">{{ passage.title }}</p>
                    <p class="passage-text">
                        <template v-for="(token, index) in highlightedPassageTokens" :key="index">
                            <span
                                :class="{
                                    'passage-token-incorrect': token.status === 'incorrect' || token.status === 'missing',
                                    'passage-token-semantic': token.status === 'semantic',
                                }"
                            >{{ token.text }}</span>
                        </template>
                    </p>
                </div>
            </template>

            <template #recorder>
                <AudioRecorder
                    presentation="hold-circle"
                    :max-duration-seconds="60"
                    :min-duration-seconds="1"
                    :require-review-before-submit="false"
                    :auto-transcribe-on-stop="false"
                    :submitting="uploading"
                    :submitted="Boolean(form.audio_file_id) && !uploadError"
                    :pulse-active="agentSpeaking"
                    label="Passage voice"
                    prompt-type="passage"
                    @recorded="rememberAudio"
                    @submit="uploadTranscript"
                    @cleared="clearAudio"
                />
            </template>

            <template #transcript>
                <AsrTranscriptVisualizer
                    :transcript="transcript"
                    :expected-text="passage?.prompt ?? ''"
                    :asr-result="asrResult"
                    :is-processing="uploading"
                    :error="uploadError"
                    normal-mode="div"
                    placeholder="Transcript will appear here"
                    box-class="min-h-0 h-full w-full flex-1 overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 text-xl font-black leading-tight text-slate-800 transition"
                >
                    <template #normal="{ placeholder }">
                        <div class="passage-transcript-words">
                            <span v-if="transcript">
                                <template v-for="(word, index) in diff.actualWords" :key="`${word.index}-${index}`">
                                    <span
                                        class="passage-transcript-token"
                                        :class="{
                                            'passage-transcript-token--incorrect': diff.actualStatus[index] === 'incorrect' || diff.actualStatus[index] === 'extra',
                                            'passage-transcript-token--semantic': diff.actualStatus[index] === 'semantic',
                                        }"
                                    >{{ word.raw }}</span>
                                </template>
                            </span>
                            <span v-else class="passage-transcript-empty">
                                <span class="passage-transcript-empty-icon" aria-hidden="true">
                                    <MessageSquareText class="size-5" />
                                </span>
                                <span class="passage-transcript-empty-copy">
                                    <span class="passage-transcript-empty-title">{{ placeholder }}</span>
                                    <span class="passage-transcript-empty-helper">Your spoken answer will be transcribed here.</span>
                                </span>
                            </span>
                        </div>
                    </template>
                </AsrTranscriptVisualizer>
            </template>

            <template #status>
                <p v-if="diff.semanticCount > 0" class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-black text-amber-700 ring-1 ring-amber-200/60">
                    {{ diff.semanticCount }} meaning-preserving word {{ diff.semanticCount === 1 ? 'swap was' : 'swaps were' }} understood and not counted as a full mismatch.
                </p>
                <p v-if="uploadError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">
                    {{ uploadError }}
                </p>
            </template>

            <template v-if="canUseManualFallback" #qa>
                <label class="flex min-w-0 flex-1 items-center gap-2 text-xs font-black text-slate-500">
                    <span class="shrink-0">Developer QA: Incorrect Words Override</span>
                    <input
                        v-model="form.incorrect_words"
                        type="number"
                        min="0"
                        max="50"
                        class="min-h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-800 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                    >
                </label>
            </template>
        </AssessmentTaskWorkspace>
    </LearnerLayout>
</template>

<style scoped>
.passage-prompt {
    display: grid;
    width: min(100%, 66rem);
    height: 100%;
    max-height: 100%;
    min-width: 0;
    align-content: center;
    justify-items: center;
    gap: clamp(0.35rem, 1dvh, 0.7rem);
    overflow: hidden;
    text-align: left;
}

.passage-title {
    margin: 0;
    width: 100%;
    text-align: center;
    font-size: clamp(0.8rem, 1.6dvh, 1rem);
    font-weight: 900;
    color: rgb(37 99 235);
}

.passage-text {
    margin: 0;
    max-width: 100%;
    color: rgb(30 41 59);
    font-size: clamp(0.85rem, min(9cqh, 2.35cqw), 1.5rem);
    font-weight: 900;
    line-height: 1.35;
    overflow-wrap: anywhere;
    text-wrap: pretty;
    word-break: normal;
}

.passage-token-incorrect {
    border-radius: 0.4rem;
    background: rgb(255 241 242);
    padding: 0 0.18rem;
    color: rgb(225 29 72);
    box-shadow: inset 0 0 0 1px rgb(254 205 211);
}

.passage-token-semantic {
    border-radius: 0.4rem;
    background: rgb(255 251 235);
    padding: 0 0.18rem;
    color: rgb(180 83 9);
    box-shadow: inset 0 0 0 1px rgb(253 230 138);
}

.passage-transcript-words {
    min-height: 0;
    height: 100%;
    width: 100%;
    flex: 1 1 auto;
    overflow-y: auto;
    border: 0;
    border-radius: 24px;
    background: transparent;
    padding: clamp(0.35rem, 1dvh, 0.75rem);
    color: var(--rd-text-main);
    font-size: clamp(1rem, 2.1dvh, 1.45rem);
    font-weight: 900;
    line-height: 1.35;
}

.passage-transcript-token {
    display: inline-block;
    margin-right: 0.5rem;
    border-radius: 0.55rem;
    padding: 0 0.18rem;
}

.passage-transcript-token--incorrect {
    background: rgba(119, 47, 26, 0.08);
    color: var(--rd-wrong-red);
    box-shadow: inset 0 0 0 1px rgba(119, 47, 26, 0.18);
}

.passage-transcript-token--semantic {
    background: rgba(238, 193, 112, 0.22);
    color: var(--rd-brown);
    box-shadow: inset 0 0 0 1px rgba(238, 193, 112, 0.42);
}

.passage-transcript-empty {
    display: flex;
    height: 100%;
    align-items: center;
    gap: clamp(0.85rem, 1.5vw, 1.25rem);
}

.passage-transcript-empty-icon {
    display: grid;
    width: clamp(2.6rem, 5.5dvh, 3.35rem);
    height: clamp(2.6rem, 5.5dvh, 3.35rem);
    flex: 0 0 auto;
    place-items: center;
    border: 2px solid var(--rd-story-border-soft);
    border-radius: 999px;
    background: var(--rd-story-surface);
    color: var(--rd-brown);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.18), 0 8px 14px rgba(54, 83, 101, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.passage-transcript-empty-copy {
    display: grid;
    min-width: 0;
    gap: 0.25rem;
}

.passage-transcript-empty-title {
    color: var(--rd-text-main);
    font-size: clamp(1rem, 2.3dvh, 1.35rem);
    font-weight: 900;
    line-height: 1.15;
}

.passage-transcript-empty-helper {
    color: rgba(111, 101, 52, 0.68);
    font-size: clamp(0.82rem, 1.8dvh, 1rem);
    font-weight: 800;
    line-height: 1.25;
}
</style>
