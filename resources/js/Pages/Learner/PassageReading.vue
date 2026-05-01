<script setup>
import { computed, ref } from 'vue';
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
});

const form = useForm({ incorrect_words: 0, audio: null, audio_file_id: null, duration_seconds: null });
const audioFile = ref(null);
const transcript = ref('');
const uploadError = ref('');
const uploading = ref(false);

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

const canSubmit = computed(() => {
    const hasIncorrectWords = form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;

    return !uploading.value && hasIncorrectWords && (Boolean(form.audio_file_id) || Boolean(form.audio));
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
            throw new Error(result.message ?? 'Unable to transcribe the recording right now.');
        }

        form.audio_file_id = result.audio_file_id ?? null;
        transcript.value = String(result.displayed_transcript ?? result.corrected_transcript ?? result.transcript ?? result.raw_transcript ?? '').trim();

        if (transcript.value !== '') {
            form.incorrect_words = diff.value.incorrectCount;
            return;
        }

        uploadError.value = result.transcription_message ?? result.message ?? 'No transcript was produced. You can still enter a fallback incorrect-word count manually.';
    } catch (error) {
        uploadError.value = error.message || 'Unable to transcribe the recording right now.';
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
    uploadTranscript(file);
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
                :message="uploading ? 'Transcribing your reading and checking it against the passage.' : 'Read the passage aloud. I will transcribe what you actually said, mark true mismatches in red, and show meaning-preserving swaps in orange.'"
            />
        </template>
        <div class="mx-auto grid max-w-2xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge status="50 words" />
                <StatusBadge :status="uploading ? 'Transcribing' : 'Max 60 seconds'" :variant="uploading ? 'primary' : 'warning'" />
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
                    label="Passage voice"
                    @recorded="rememberAudio"
                    @cleared="clearAudio"
                />
                <div class="grid gap-3">
                    <label class="grid gap-2 text-lg font-black text-text">
                        Transcript
                        <div class="min-h-16 rounded-2xl border-2 border-border px-4 py-3 text-base font-black text-text">
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
                                {{ uploading ? 'Generating transcript...' : 'Transcript appears here after recording' }}
                            </span>
                        </div>
                    </label>
                    <label class="grid content-center gap-2 text-lg font-black text-text">
                        Incorrect words
                        <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none">
                    </label>
                    <p v-if="diff.semanticCount > 0" class="rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">
                        {{ diff.semanticCount }} meaning-preserving word {{ diff.semanticCount === 1 ? 'swap was' : 'swaps were' }} understood and not counted as a full mismatch.
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
