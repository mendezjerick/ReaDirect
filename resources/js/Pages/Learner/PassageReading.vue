<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

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
const uploadError = ref('');
const uploading = ref(false);
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);

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

const buildDiff = (expectedText, actualText) => {
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

const diff = computed(() => buildDiff(props.passage?.prompt ?? '', transcript.value));
const incorrectWordCount = computed(() => diff.value.incorrectCount);
const incorrectHighlightCount = computed(() => diff.value.expectedStatus.filter((status) => status === 'incorrect' || status === 'missing').length);
const extraWordCount = computed(() => diff.value.actualStatus.filter((status) => status === 'extra').length);
const exactMatchCount = computed(() => diff.value.expectedStatus.filter((status) => status === 'correct').length);
const hasTranscriptAnalysis = computed(() => transcript.value.trim() !== '');
const highlightedPassageTokens = computed(() => {
    const tokens = wordTokens(props.passage?.prompt ?? '');
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

watch(transcript, (value) => {
    form.incorrect_words = value.trim() !== '' ? diff.value.incorrectCount : 0;
}, { immediate: true });

const canSubmit = computed(() => {
    const hasIncorrectWords = form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
    const hasRecording = Boolean(form.audio_file_id) || Boolean(form.audio);

    if (canUseManualFallback.value) {
        return !uploading.value && hasIncorrectWords && hasRecording;
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

        form.audio_file_id = result.audio_file_id ?? null;
        transcript.value = String(result.displayed_transcript ?? result.corrected_transcript ?? result.transcript ?? result.raw_transcript ?? '').trim();

        if (transcript.value === '') {
            uploadError.value = result.transcription_message ?? result.message ?? 'We could not hear your reading clearly. Please try recording again.';
        }
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
    uploadError.value = '';
};

const clearAudio = () => {
    audioFile.value = null;
    form.audio = null;
    form.audio_file_id = null;
    form.duration_seconds = null;
    transcript.value = '';
    uploadError.value = '';
};

const submit = () => form.post('/learner/diagnostic/passage', { forceFormData: true });
</script>

<template>
    <LearnerLayout :progress="78">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                :state="uploading ? 'speaking' : 'listening'"
                :message="uploading ? 'Checking your reading.' : 'Read the passage aloud. Try your best and speak clearly.'"
            />
        </template>
        <div class="mx-auto grid max-w-2xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge status="50 words" />
                <StatusBadge :status="uploading ? 'Checking' : 'Max 60 seconds'" :variant="uploading ? 'primary' : 'warning'" />
            </div>
            <section class="max-h-[34vh] overflow-y-auto rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10 lg:max-h-[42vh]">
                <p class="text-2xl font-black leading-relaxed text-text md:text-[28px]">
                    <template v-for="(token, index) in highlightedPassageTokens" :key="index">
                        <span
                            :class="{
                                'rounded-lg bg-danger/15 px-1 text-danger': token.status === 'incorrect' || token.status === 'missing',
                                'rounded-lg bg-warning/15 px-1 text-warning': token.status === 'semantic',
                            }"
                        >{{ token.text }}</span>
                    </template>
                </p>
            </section>
            <div class="grid gap-3 rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10 md:grid-cols-[220px_1fr]">
                <AudioRecorder
                    compact
                    :max-duration-seconds="60"
                    :require-review-before-submit="requireReviewBeforeSubmit"
                    :auto-transcribe-on-stop="autoTranscribeOnStop"
                    :submitting="uploading"
                    :submitted="Boolean(form.audio_file_id) && !uploadError"
                    label="Passage voice"
                    @recorded="rememberAudio"
                    @submit="uploadTranscript"
                    @cleared="clearAudio"
                />
                <div class="grid gap-3">
                    <div v-if="hasTranscriptAnalysis" class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-2xl border border-danger/20 bg-danger/10 px-4 py-3">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-danger/80">Incorrect</p>
                            <p class="mt-1 text-2xl font-black text-danger">{{ incorrectWordCount }}</p>
                            <p class="text-xs font-bold text-danger/80">Words counted as incorrect</p>
                        </div>
                        <div class="rounded-2xl border border-primary/15 bg-primaryLight/50 px-4 py-3">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-primaryDark/80">Correct</p>
                            <p class="mt-1 text-2xl font-black text-primaryDark">{{ exactMatchCount }}</p>
                            <p class="text-xs font-bold text-primaryDark/80">Words matched clearly</p>
                        </div>
                        <div class="rounded-2xl border border-warning/20 bg-warning/10 px-4 py-3">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-warning/80">Review</p>
                            <p class="mt-1 text-2xl font-black text-warning">{{ incorrectHighlightCount + extraWordCount }}</p>
                            <p class="text-xs font-bold text-warning/80">Highlighted for review</p>
                        </div>
                    </div>
                    <label class="grid gap-2 text-lg font-black text-text">
                        You said
                        <div class="learner-transcript-box rounded-2xl border-2 border-border font-black text-text">
                            <span v-if="transcript">
                                <template v-for="(word, index) in diff.actualWords" :key="`${word.index}-${index}`">
                                    <span
                                        class="mr-2 inline-block rounded-lg px-1"
                                        :class="{
                                            'bg-danger/15 text-danger': diff.actualStatus[index] === 'incorrect' || diff.actualStatus[index] === 'extra',
                                            'bg-warning/15 text-warning': diff.actualStatus[index] === 'semantic',
                                        }"
                                    >{{ word.raw }}</span>
                                </template>
                            </span>
                            <span v-else class="text-muted">
                                {{ uploading ? 'Checking your recording...' : 'Your words will appear here' }}
                            </span>
                        </div>
                    </label>
                    <label v-if="canUseManualFallback" class="grid content-center gap-2 text-lg font-black text-text">
                        Developer QA: Incorrect Words Override
                        <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none">
                    </label>
                    <p v-if="diff.semanticCount > 0" class="rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">
                        {{ diff.semanticCount }} meaning-preserving word {{ diff.semanticCount === 1 ? 'swap was' : 'swaps were' }} understood and not counted as a full mismatch.
                    </p>
                    <p v-if="hasTranscriptAnalysis" class="rounded-2xl bg-primaryLight/60 px-4 py-3 text-sm font-black text-primaryDark">
                        <span v-if="incorrectWordCount === 0">No incorrect words were detected in this reading.</span>
                        <span v-else>{{ incorrectWordCount }} incorrect {{ incorrectWordCount === 1 ? 'word was' : 'words were' }} detected. Red highlights show what needs attention.</span>
                    </p>
                    <p v-if="uploadError" class="rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">
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
