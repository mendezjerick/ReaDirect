<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';

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
    : 'This is your final reading check. Read the passage aloud and try your best.'));
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);
const hasIncorrectWords = () => form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
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
const incorrectWordsFromAlignment = (alignment) => {
    if (!Array.isArray(alignment) || alignment.length === 0) return null;
    const expectedEntries = alignment.filter((item) => item?.expected_word !== null && item?.expected_word !== undefined);
    if (expectedEntries.length === 0) return null;

    return expectedEntries.filter((item) => !acceptedAlignmentStatuses.has(item?.status)).length;
};
const initialAlignedIncorrectWords = incorrectWordsFromAlignment(wordAlignment.value);
if (initialAlignedIncorrectWords !== null) {
    form.incorrect_words = initialAlignedIncorrectWords;
}
const canSubmit = computed(() => {
    if (canUseManualFallback.value) {
        return !uploading.value && hasIncorrectWords();
    }

    return !uploading.value && Boolean(form.audio_file_id) && transcript.value.trim() !== '';
});
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
            const alignedIncorrectWords = incorrectWordsFromAlignment(asr.wordAlignment);
            if (alignedIncorrectWords !== null) {
                form.incorrect_words = alignedIncorrectWords;
            }
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
const submit = () => {
    if (canUseManualFallback.value && !form.audio_file_id) {
        form.audio = null;
        form.duration_seconds = null;
    }

    form.post('/final-assessment/passage/submit', { forceFormData: true });
};
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel
                compact
                agent-type="assessment"
                :state="agentState"
                :message="agentMessage"
            />
        </template>

        <section class="relative mx-auto grid w-full max-w-[960px] gap-4 sm:gap-5 xl:gap-6">
            <span class="pointer-events-none absolute -left-14 top-12 hidden text-4xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute -right-8 bottom-12 hidden text-3xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <div class="pointer-events-none absolute -left-20 top-0 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 bottom-0 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" aria-hidden="true" />

            <!-- Progress header -->
            <div class="anim-fade-down grid gap-3 px-1">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-primary/5 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10">
                        📜 50 words
                    </span>
                    <span class="rounded-full bg-amber-50 px-3.5 py-1.5 text-[13px] font-black text-amber-600 ring-1 ring-amber-200/60">
                        ⏱️ Max 60 seconds
                    </span>
                </div>
                <div class="h-3.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div class="h-full w-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30" />
                </div>
            </div>

            <!-- Passage card -->
            <section class="anim-card relative max-h-[34vh] overflow-y-auto overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-6 shadow-2xl shadow-primary/10 sm:p-7 lg:max-h-[42vh]" aria-label="Reading passage">
                <p class="text-2xl font-black leading-relaxed text-slate-800 md:text-[28px]">{{ passage.prompt }}</p>
            </section>

            <!-- Recording card -->
            <div class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-6 shadow-2xl shadow-primary/10 sm:p-7">
                <div class="grid gap-4 md:grid-cols-[220px_1fr]">
                    <AudioRecorder
                        compact
                        :max-duration-seconds="60"
                        prompt-type="passage"
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
                        <label class="grid gap-2 text-lg font-black text-text">
                            You said
                            <div class="learner-transcript-box rounded-2xl border-2 border-border font-black text-text">
                                <span v-if="transcript">{{ transcript }}</span>
                                <span v-else class="text-muted">{{ uploading ? 'Checking your recording...' : 'Your words will appear here' }}</span>
                            </div>
                        </label>
                        <label v-if="canUseManualFallback" class="grid content-center gap-2 text-lg font-black text-text">
                            Developer QA: Incorrect Words Override
                            <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none">
                        </label>
                        <p v-if="uploadError" class="rounded-[20px] bg-amber-50 px-4 py-3 text-[13px] font-semibold text-amber-700 ring-1 ring-amber-200/60">{{ uploadError }}</p>
                    </div>
                </div>
            </div>
        </section>

        <BottomActionBar>
            <PrimaryButton :disabled="form.processing || !canSubmit" @click="submit">Continue</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
