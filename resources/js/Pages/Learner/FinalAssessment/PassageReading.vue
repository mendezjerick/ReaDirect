<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

const props = defineProps({
    passage: Object,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});

const form = useForm({ incorrect_words: 0, audio: null, audio_file_id: null, duration_seconds: null });
const audioFile = ref(null);
const transcript = ref('');
const uploadError = ref('');
const uploading = ref(false);
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const hasIncorrectWords = () => form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
const canSubmit = computed(() => {
    if (canUseManualFallback.value) {
        return !uploading.value && (Boolean(form.audio_file_id) || Boolean(form.audio)) && hasIncorrectWords();
    }

    return !uploading.value && Boolean(form.audio_file_id) && transcript.value.trim() !== '';
});
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
        transcript.value = String(result.displayed_transcript ?? result.transcript ?? '').trim();

        if (!transcript.value) {
            uploadError.value = result.transcription_message ?? result.message ?? 'We could not hear your reading clearly. Please try recording again.';
        }
    } catch (error) {
        uploadError.value = error.message || 'We had trouble checking your reading. Please try again.';
    } finally {
        uploading.value = false;
    }
};
const submit = () => form.post('/final-assessment/passage/submit', { forceFormData: true });
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel agent-type="assessment" state="listening" message="This is your final reading check. Read the passage aloud and try your best." />
        </template>
        <div class="mx-auto grid max-w-2xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge status="50 words" />
                <StatusBadge status="Max 60 seconds" variant="warning" />
            </div>
            <section class="max-h-[34vh] overflow-y-auto rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10 lg:max-h-[42vh]">
                <p class="text-2xl font-black leading-relaxed text-text md:text-[28px]">{{ passage.prompt }}</p>
            </section>
            <div class="grid gap-3 rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10 md:grid-cols-[220px_1fr]">
                <AudioRecorder
                    compact
                    :max-duration-seconds="60"
                    :require-review-before-submit="!isDeveloperQaMode"
                    :auto-transcribe-on-stop="isDeveloperQaMode"
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
                    <p v-if="uploadError" class="rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">{{ uploadError }}</p>
                </div>
            </div>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing || !canSubmit" @click="submit">Continue</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
