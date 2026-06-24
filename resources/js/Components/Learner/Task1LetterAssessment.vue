<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from './AssessmentTaskWorkspace.vue';
import AudioRecorder from './AudioRecorder.vue';
import AsrTranscriptVisualizer from './AsrTranscriptVisualizer.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';

const props = defineProps({
    items: { type: Array, default: () => [] },
    initialIndex: { type: Number, default: 0 },
    assessmentAttemptId: { type: Number, required: true },
    assessmentMode: { type: Object, default: () => ({}) },
    submitUrl: { type: String, required: true },
    initialAgentMessage: { type: String, default: 'Say the letter out loud. Record your answer when you are ready.' },
    submitErrorMessage: { type: String, default: 'We could not check these answers yet. Please review the letters and try again.' },
    continueMessages: {
        type: Array,
        default: () => ['Thank you. Let us continue.', 'Good effort. Let us go to the next one.', 'I heard your answer. Let us keep going.'],
    },
});

const form = useForm({ assessment_attempt_id: props.assessmentAttemptId, responses: [] });
const audioFiles = reactive({});
const audioDurations = reactive({});
const savedEntries = (key) => Object.fromEntries((props.items ?? [])
    .filter((item) => item?.saved_response?.[key] != null && item.saved_response[key] !== '')
    .map((item) => [item.id, item.saved_response[key]]));
const uploadedAudioIds = reactive(savedEntries('audio_file_id'));
const transcriptSources = reactive(savedEntries('transcript_source'));
const generatedTranscripts = reactive(Object.fromEntries((props.items ?? [])
    .filter((item) => item?.saved_response?.answer || item?.saved_response?.displayed_transcript)
    .map((item) => [item.id, item.saved_response.displayed_transcript ?? item.saved_response.answer])));
const checkedItems = reactive(Object.fromEntries((props.items ?? [])
    .filter((item) => item?.saved_response?.answer || item?.saved_response?.displayed_transcript || item?.saved_response?.audio_file_id)
    .map((item) => [item.id, true])));
const asrResults = reactive({});
const uploadErrors = reactive({});
const uploading = reactive({});
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const manualAnswerFor = (item) => canUseManualFallback.value ? String(step.answers[item?.id] ?? '').trim() : '';
const answerFor = (item) => manualAnswerFor(item) || String(generatedTranscripts[item?.id] ?? '').trim();
const sourceFor = (item) => manualAnswerFor(item)
    ? 'manual'
    : (transcriptSources[item?.id] ?? (generatedTranscripts[item?.id] ? 'stt_auto' : 'stt_auto'));
const hasAnswerOrAudio = (item) => answerFor(item).length > 0;
const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.', initialIndex: props.initialIndex ?? 0, isAnswered: hasAnswerOrAudio });
const agentMessage = ref(props.initialAgentMessage);
const agentState = ref('listening');
const agentSpeaking = ref(false);
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');
const isCurrentChecked = computed(() => Boolean(checkedItems[step.currentItem.value?.id]) && hasAnswerOrAudio(step.currentItem.value));
const canSubmitCurrent = computed(() => {
    const item = step.currentItem.value;

    if (!item) return false;

    return Boolean(audioFiles[item.id]) || manualAnswerFor(item).length > 0;
});
const primaryLabel = computed(() => isCurrentChecked.value ? 'Next' : 'Submit');
const primaryDisabled = computed(() => form.processing || isCurrentUploading.value || (!isCurrentChecked.value && !canSubmitCurrent.value));

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
    delete checkedItems[item.id];
    agentMessage.value = 'Listen to your answer. If you are happy with your answer, click Submit.';
    agentState.value = 'speaking';
};

const clearAudio = (item) => {
    delete audioFiles[item.id];
    delete audioDurations[item.id];
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
    delete uploadErrors[item.id];
    delete uploading[item.id];
    delete checkedItems[item.id];
};

const setAnswer = (item, value) => {
    step.answers[item.id] = value;
};

const uploadAudio = async (item, file) => {
    if (uploading[item.id] || form.processing) {
        return false;
    }

    uploading[item.id] = true;
    uploadErrors[item.id] = '';
    agentMessage.value = 'Checking your recording.';
    agentState.value = 'thinking';

    try {
        const payload = new FormData();
        payload.append('audio', file);
        payload.append('context_type', 'assessment_task');
        payload.append('assessment_attempt_id', String(props.assessmentAttemptId));
        payload.append('item_id', String(item.id));
        payload.append('task_type', 'crla_task_1_letter');
        if (audioDurations[item.id] != null) {
            payload.append('duration_seconds', String(audioDurations[item.id]));
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
        asrResults[item.id] = result;

        if (!response.ok) {
            throw new Error(result.message ?? 'We had trouble checking your answer. Please try again.');
        }

        const asr = normalizeAsrResponse(result);
        if (asr.canSubmit) {
            uploadedAudioIds[item.id] = result.audio_file_id;
            const transcript = asr.displayTranscript;
            generatedTranscripts[item.id] = transcript;
            transcriptSources[item.id] = result.transcript_source ?? 'stt_auto';
            checkedItems[item.id] = true;
            agentMessage.value = `You said: ${transcript}`;
            agentState.value = 'speaking';
            return true;
        }

        delete checkedItems[item.id];
        uploadErrors[item.id] = asr.message;
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'retry';
        return false;
    } catch (error) {
        delete checkedItems[item.id];
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'retry';
        return false;
    } finally {
        uploading[item.id] = false;
    }
};

const submitCurrentForReview = async () => {
    const item = step.currentItem.value;

    if (!item || isCurrentUploading.value || form.processing) {
        return;
    }

    const manualAnswer = manualAnswerFor(item);
    if (manualAnswer) {
        generatedTranscripts[item.id] = manualAnswer;
        transcriptSources[item.id] = 'manual';
        checkedItems[item.id] = true;
        uploadErrors[item.id] = '';
        agentMessage.value = `You said: ${manualAnswer}`;
        agentState.value = 'speaking';
        return;
    }

    const file = audioFiles[item.id];
    if (!file) {
        agentMessage.value = 'Hold the blue button to record your answer first.';
        agentState.value = 'speaking';
        return;
    }

    await uploadAudio(item, file);
};

const submit = () => {
    if (!step.validateComplete()) {
        agentMessage.value = 'Almost there. Finish each letter before checking your answer.';
        agentState.value = 'speaking';
        return;
    }

    form.responses = step.payload((item) => ({
        assessment_attempt_item_id: item.id,
        answer: answerFor(item),
        transcript_source: sourceFor(item),
        audio_file_id: uploadedAudioIds[item.id] ?? null,
        audio: uploadedAudioIds[item.id] ? null : (audioFiles[item.id] ?? null),
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post(props.submitUrl, {
        forceFormData: true,
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? props.submitErrorMessage;
            step.feedback.value = Array.isArray(firstError) ? firstError[0] : firstError;
            agentMessage.value = step.feedback.value;
            agentState.value = 'retry';
        },
    });
};

const goNextOrFinish = () => {
    if (!isCurrentChecked.value) {
        agentMessage.value = 'Click Submit first so I can check your answer.';
        agentState.value = 'speaking';
        return;
    }

    if (!step.validateCurrent()) {
        agentMessage.value = 'Let us answer this first.';
        agentState.value = 'speaking';
        return;
    }

    agentMessage.value = props.continueMessages[step.currentIndex.value % props.continueMessages.length] ?? 'Thank you. Let us continue.';
    agentState.value = 'speaking';

    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};

const handlePrimary = () => {
    if (isCurrentChecked.value) {
        goNextOrFinish();
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
            :progress="step.progressPercent.value"
            :primary-label="primaryLabel"
            :primary-disabled="primaryDisabled"
            @primary="handlePrimary"
            @agent-speaking-change="setAgentSpeaking"
        >
            <template #prompt>
                <div :key="step.currentItem.value.id" class="letter-prompt">
                    {{ step.currentItem.value.prompt }}
                </div>
            </template>

            <template #recorder>
                <AudioRecorder
                    :key="step.currentItem.value.id"
                    :reset-key="step.currentItem.value.id"
                    presentation="hold-circle"
                    :max-duration-seconds="30"
                    :min-duration-seconds="0.5"
                    :require-review-before-submit="false"
                    :auto-transcribe-on-stop="false"
                    :submitting="isCurrentUploading"
                    :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                    :pulse-active="agentSpeaking"
                    label="Letter voice"
                    prompt-type="letter"
                    @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                    @submit="(file) => uploadAudio(step.currentItem.value, file)"
                    @cleared="() => clearAudio(step.currentItem.value)"
                />
            </template>

            <template #transcript>
                <AsrTranscriptVisualizer
                    :transcript="generatedTranscripts[step.currentItem.value.id] ?? ''"
                    :expected-text="step.currentItem.value.payload?.expected_answer ?? step.currentItem.value.prompt"
                    :asr-result="asrResults[step.currentItem.value.id]"
                    :is-processing="isCurrentUploading"
                    :error="uploadErrors[step.currentItem.value.id] ?? ''"
                    placeholder="Transcript will appear here"
                    box-class="min-h-0 h-full w-full flex-1 resize-none overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 text-2xl font-black leading-tight text-slate-800 transition placeholder:text-slate-300 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                />
            </template>

            <template #status>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">
                    {{ uploadErrors[step.currentItem.value.id] }}
                </p>
                <p v-if="firstFormError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">
                    {{ firstFormError }}
                </p>
                <p v-if="step.feedback.value" class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-black text-amber-700 ring-1 ring-amber-200/60">
                    {{ step.feedback.value }}
                </p>
            </template>

            <template v-if="canUseManualFallback || (canUseDeveloperJumpControls && !step.isFirst.value)" #qa>
                <div class="flex items-center gap-2">
                    <button
                        v-if="canUseDeveloperJumpControls && !step.isFirst.value"
                        type="button"
                        class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600 hover:border-primary/30 hover:bg-primary-light hover:text-primary"
                        @click="step.goBack"
                    >
                        Back
                    </button>
                    <label v-if="canUseManualFallback" class="flex min-w-0 flex-1 items-center gap-2 text-xs font-black text-slate-500">
                        <span class="shrink-0">Developer QA: Manual Transcript Override</span>
                        <input
                            :value="step.answers[step.currentItem.value.id]"
                            class="min-h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-800 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                            placeholder="Optional QA fallback text"
                            @input="setAnswer(step.currentItem.value, $event.target.value)"
                        >
                    </label>
                </div>
            </template>
        </AssessmentTaskWorkspace>
    </LearnerLayout>
</template>

<style scoped>
.letter-prompt {
    display: grid;
    min-width: 0;
    place-items: center;
    font-size: clamp(5rem, 22dvh, 16rem);
    font-weight: 900;
    line-height: 0.9;
    color: rgb(100 116 139);
}
</style>
