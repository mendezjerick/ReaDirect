<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    items: Array,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});
const form = useForm({ responses: [] });
const audioFiles = reactive({});
const audioDurations = reactive({});
const uploadedAudioIds = reactive({});
const transcriptSources = reactive({});
const generatedTranscripts = reactive({});
const uploadErrors = reactive({});
const uploading = reactive({});
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const manualAnswerFor = (item) => canUseManualFallback.value ? String(step.answers[item?.id] ?? '').trim() : '';
const answerFor = (item) => manualAnswerFor(item) || String(generatedTranscripts[item?.id] ?? '').trim();
const sourceFor = (item) => manualAnswerFor(item)
    ? 'manual'
    : (transcriptSources[item?.id] ?? (generatedTranscripts[item?.id] ? 'stt_auto' : 'stt_auto'));
const hasAnswerOrAudio = (item) => answerFor(item).length > 0;
const step = useStepAssessment(props.items, { emptyMessage: 'Let us answer this first.', isAnswered: hasAnswerOrAudio });
const targetWordFor = (item) => item?.payload?.target_word ?? item?.payload?.expected_answer ?? item?.accepted_answers?.[0] ?? '';
const agentMessage = ref('Listen to the word. Say the word that matches the rhyme.');
const agentState = ref('listening');
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    agentMessage.value = 'Listen to your answer. If you are happy with your answer, click Submit.';
    agentState.value = 'speaking';
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
const setAnswer = (item, value) => {
    step.answers[item.id] = value;
};
const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    agentMessage.value = 'Checking your recording.';
    agentState.value = 'speaking';

    try {
        const payload = new FormData();
        payload.append('audio', file);
        payload.append('context_type', 'assessment_task');
        payload.append('assessment_attempt_id', String(props.assessmentAttemptId));
        payload.append('item_id', String(item.id));
        payload.append('task_type', 'crla_task_2a_rhyme');
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

        const transcript = String(result.displayed_transcript ?? result.corrected_transcript ?? result.transcript ?? result.raw_transcript ?? '').trim();
        uploadedAudioIds[item.id] = result.audio_file_id;
        if (transcript) {
            generatedTranscripts[item.id] = transcript;
            transcriptSources[item.id] = result.transcript_source ?? 'stt_auto';
            agentMessage.value = `You said: ${transcript}`;
            agentState.value = 'speaking';
            return;
        }

        uploadErrors[item.id] = result.transcription_message ?? result.message ?? 'We could not hear your answer clearly. Please try recording again.';
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'speaking';
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'speaking';
    } finally {
        uploading[item.id] = false;
    }
};
const submit = () => {
    if (!step.validateCurrent()) return;
    form.responses = step.payload((item) => ({
        assessment_attempt_item_id: item.id,
        answer: answerFor(item),
        transcript_source: sourceFor(item),
        audio_file_id: uploadedAudioIds[item.id] ?? null,
        audio: uploadedAudioIds[item.id] ? null : (audioFiles[item.id] ?? null),
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post('/final-assessment/task-2a/submit', { forceFormData: true });
};
const handlePrimary = () => {
    if (!step.validateCurrent()) {
        agentMessage.value = 'Let us answer this first.';
        agentState.value = 'speaking';
        return;
    }

    if (isCurrentUploading.value) {
        agentMessage.value = 'Wait for the transcript to finish loading.';
        agentState.value = 'speaking';
        return;
    }

    agentMessage.value = 'Thank you. Let us continue.';
    agentState.value = 'speaking';
    if (step.isLast.value) return submit();
    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="42">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" :state="agentState" :message="agentMessage" />
        </template>
        <section class="mx-auto grid max-w-xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge :status="`Rhyme ${step.currentIndex.value + 1} of ${items.length}`" />
                <StatusBadge :status="isCurrentUploading ? 'Checking' : 'Voice check'" variant="primary" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <div class="rounded-[28px] border border-border bg-surface p-5 text-center shadow-xl shadow-primary/10">
                <p class="text-base font-black text-muted">Read the second word</p>
                <div class="mt-3 flex items-center justify-center gap-3 text-4xl font-black leading-snug text-text md:text-5xl">
                    <span>{{ step.currentItem.value.prompt }}</span>
                    <span class="text-muted">-</span>
                    <mark class="rounded-2xl bg-accent px-3 py-1">{{ targetWordFor(step.currentItem.value) }}</mark>
                </div>
            </div>
            <div class="rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10">
                <div class="grid gap-3 md:grid-cols-[220px_1fr] md:items-center">
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        compact
                        :max-duration-seconds="30"
                        :require-review-before-submit="!isDeveloperQaMode"
                        :auto-transcribe-on-stop="isDeveloperQaMode"
                        :submitting="isCurrentUploading"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Second word voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <div class="grid gap-3">
                        <label class="grid gap-2 text-lg font-black text-text">
                            You said
                            <textarea :value="generatedTranscripts[step.currentItem.value.id] ?? ''" class="min-h-20 resize-none rounded-2xl border-2 border-border bg-background px-4 py-3 text-lg font-black text-text focus:border-primary focus:outline-none" readonly :placeholder="isCurrentUploading ? 'Checking your recording...' : 'Your words will appear here'" />
                        </label>
                        <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-muted">
                            Developer QA: Manual Transcript Override
                            <input :value="step.answers[step.currentItem.value.id]" class="w-full rounded-2xl border-2 border-border px-4 py-3 text-base font-black text-text focus:border-primary focus:outline-none" placeholder="Optional QA fallback text" @input="setAnswer(step.currentItem.value, $event.target.value)">
                        </label>
                    </div>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">{{ uploadErrors[step.currentItem.value.id] }}</p>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>
        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || isCurrentUploading" :class="{ 'opacity-70': !step.isCurrentAnswered.value || isCurrentUploading }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check words' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
