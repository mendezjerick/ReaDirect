<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import ModuleProgressBar from '../../../Components/ModuleProgressBar.vue';
import PromptCard from '../../../Components/PromptCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    module: Object,
    moduleAttemptId: Number,
    activityType: String,
    activityLabel: String,
    items: Array,
    nextActivityType: String,
    assessmentMode: Object,
});

const form = useForm({ responses: [] });
const retries = reactive({});
const audioFiles = reactive({});
const audioDurations = reactive({});
const uploadedAudioIds = reactive({});
const transcriptSources = reactive({});
const generatedTranscripts = reactive({});
const uploadErrors = reactive({});
const uploading = reactive({});
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);
const manualAnswerFor = (item, answer = null) => canUseManualFallback.value ? String(answer ?? step.answers[item?.id] ?? '').trim() : '';
const answerFor = (item, answer = null) => manualAnswerFor(item, answer) || String(generatedTranscripts[item?.id] ?? '').trim();
const sourceFor = (item, answer = null) => manualAnswerFor(item, answer)
    ? 'manual'
    : (transcriptSources[item?.id] ?? (generatedTranscripts[item?.id] ? 'stt_auto' : 'stt_auto'));
const hasAnswerOrAudio = (item, answer) => answerFor(item, answer).length > 0;
const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.', isAnswered: hasAnswerOrAudio });
const coachMessage = ref('Read the prompt, then record your voice. I will help you practice.');
const coachState = ref('speaking');
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));

const normalize = (value) => String(value ?? '').toLowerCase().trim().replace(/[^\w\s]/g, '').replace(/\s+/g, ' ');
const isAccepted = (item, answer) => item.accepted_answers.map(normalize).includes(normalize(answer));
const distance = (a, b) => {
    const left = normalize(a);
    const right = normalize(b);
    const matrix = Array.from({ length: left.length + 1 }, (_, i) => [i]);
    for (let j = 1; j <= right.length; j += 1) matrix[0][j] = j;
    for (let i = 1; i <= left.length; i += 1) {
        for (let j = 1; j <= right.length; j += 1) {
            matrix[i][j] = Math.min(
                matrix[i - 1][j] + 1,
                matrix[i][j - 1] + 1,
                matrix[i - 1][j - 1] + (left[i - 1] === right[j - 1] ? 0 : 1)
            );
        }
    }
    return matrix[left.length][right.length];
};
const similarityLabel = (expected, answer) => {
    const actual = normalize(answer);
    const target = normalize(expected);
    if (!actual) return 'blank';
    if (actual === target) return 'exact';
    const maxLength = Math.max(target.length, actual.length, 1);
    const percent = (1 - distance(target, actual) / maxLength) * 100;
    if (distance(target, actual) === 1 || percent >= 80) return 'very_close';
    if (percent >= 60) return 'close';
    if (percent >= 35) return 'somewhat_close';
    return 'far';
};
const feedbackFor = (item, answer) => {
    const expected = item.payload?.expected_answer ?? item.payload?.target_word ?? item.accepted_answers[0] ?? '';
    const label = similarityLabel(expected, answer);
    if (label === 'very_close') return 'Good try! That was very close. Let us fix one small sound.';
    if (label === 'close' || label === 'somewhat_close') return 'Great effort! You are getting close. Let us try it slowly.';
    return 'Good effort! Let us listen again and try one more time.';
};

const progressLabel = computed(() => `Activity ${step.currentIndex.value + 1} of ${props.items.length}`);

watch(
    () => props.items.map((item) => item.id).join('|'),
    () => {
        step.reset(props.items);
        Object.keys(retries).forEach((key) => delete retries[key]);
        Object.keys(audioFiles).forEach((key) => delete audioFiles[key]);
        Object.keys(audioDurations).forEach((key) => delete audioDurations[key]);
        Object.keys(uploadedAudioIds).forEach((key) => delete uploadedAudioIds[key]);
        Object.keys(transcriptSources).forEach((key) => delete transcriptSources[key]);
        Object.keys(generatedTranscripts).forEach((key) => delete generatedTranscripts[key]);
        Object.keys(uploadErrors).forEach((key) => delete uploadErrors[key]);
        Object.keys(uploading).forEach((key) => delete uploading[key]);
        coachMessage.value = 'Read the prompt, then record your voice. I will help you practice.';
        coachState.value = 'speaking';
        form.clearErrors();
        form.responses = [];
    }
);

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    coachMessage.value = 'Listen to your answer. If you are happy with your answer, click Submit.';
    coachState.value = 'speaking';
};

const clearAudio = (item) => {
    delete audioFiles[item.id];
    delete audioDurations[item.id];
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete uploadErrors[item.id];
    delete uploading[item.id];
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    coachMessage.value = 'Checking your recording.';
    coachState.value = 'speaking';

    try {
        const payload = new FormData();
        payload.append('audio', file);
        payload.append('context_type', 'module_activity');
        payload.append('module_attempt_id', String(props.moduleAttemptId));
        payload.append('item_id', String(item.id));
        payload.append('activity_type', props.activityType);
        payload.append('task_type', 'module_activity');
        if (audioDurations[item.id] != null) {
            payload.append('duration_seconds', String(audioDurations[item.id]));
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

        const transcript = String(result.displayed_transcript ?? result.transcript ?? '').trim();
        uploadedAudioIds[item.id] = result.audio_file_id;
        if (transcript) {
            generatedTranscripts[item.id] = transcript;
            transcriptSources[item.id] = result.transcript_source ?? 'stt_auto';
            step.feedback.value = '';
            coachMessage.value = `You said: ${transcript}`;
            coachState.value = 'speaking';
            return;
        }

        uploadErrors[item.id] = result.transcription_message ?? result.message ?? 'We could not hear your answer clearly. Please try recording again.';
        coachMessage.value = uploadErrors[item.id];
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        coachMessage.value = uploadErrors[item.id];
    } finally {
        uploading[item.id] = false;
    }
};

const tryCurrent = () => {
    if (!step.validateCurrent()) {
        coachMessage.value = 'Let us answer this first.';
        coachState.value = 'encouraging';
        return false;
    }

    const item = step.currentItem.value;
    const answer = answerFor(item);

    if (!answer && audioFiles[item.id]) {
        step.feedback.value = 'Click Submit after you listen to your recording.';
        coachMessage.value = 'Listen to your answer. If you are happy with your answer, click Submit.';
        coachState.value = 'speaking';
        return false;
    }

    if (!canUseManualFallback.value) {
        step.feedback.value = 'Answer saved.';
        return true;
    }

    if (!isAccepted(item, answer)) {
        retries[item.id] = (retries[item.id] ?? 0) + 1;
        const message = feedbackFor(item, answer);
        step.feedback.value = message;
        coachMessage.value = message;
        coachState.value = message.includes('close') ? 'encouraging' : 'thinking';
        return false;
    }

    step.feedback.value = 'Nice reading!';
    coachMessage.value = 'Nice reading! Keep going.';
    coachState.value = 'happy';
    return true;
};

const submit = () => {
    if (!tryCurrent()) return;

    form.responses = step.payload((item, answer) => ({
        module_attempt_item_id: item.id,
        answer: answerFor(item, answer),
        retry_count: retries[item.id] ?? 0,
        transcript_source: sourceFor(item, answer),
        audio_file_id: uploadedAudioIds[item.id] ?? null,
        audio: uploadedAudioIds[item.id] ? null : (audioFiles[item.id] ?? null),
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post(`/learner/modules/${props.module.key}/activity/${props.activityType}`, { forceFormData: true });
};

const handlePrimary = () => {
    if (!tryCurrent()) return;

    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="82">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="coach_feedback" :state="coachState" :message="coachMessage" />
        </template>

        <section class="mx-auto grid max-w-xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge :status="activityLabel" variant="primary" />
                <StatusBadge :status="progressLabel" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard label="Practice" :prompt="step.currentItem.value.prompt" size="word" />
            <div class="rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10">
                <div class="grid gap-3 md:grid-cols-[220px_1fr] md:items-center">
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        compact
                        :max-duration-seconds="45"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="isCurrentUploading"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Practice voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <div class="grid gap-3">
                        <label class="grid gap-2 text-lg font-black text-text">
                            You said
                            <textarea :value="generatedTranscripts[step.currentItem.value.id] ?? ''" class="learner-transcript-box resize-none rounded-2xl border-2 border-border bg-background font-black text-text focus:border-primary focus:outline-none" readonly :placeholder="isCurrentUploading ? 'Checking your recording...' : 'Your words will appear here'" />
                        </label>
                        <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-muted">
                            Developer QA: Manual Transcript Override
                            <input v-model="step.answers[step.currentItem.value.id]" class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none" placeholder="Optional QA fallback text">
                        </label>
                    </div>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">{{ uploadErrors[step.currentItem.value.id] }}</p>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-primaryLight px-4 py-3 text-lg font-black text-primaryDark">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || isCurrentUploading" :class="{ 'opacity-70': !step.isCurrentAnswered.value || isCurrentUploading }" @click="handlePrimary">
                    {{ step.isLast.value ? (nextActivityType ? 'Finish activity' : 'Start mastery check') : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
