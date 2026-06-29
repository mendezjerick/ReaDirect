<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from './AssessmentTaskWorkspace.vue';
import AudioRecorder from './AudioRecorder.vue';
import AsrTranscriptVisualizer from './AsrTranscriptVisualizer.vue';
import { useAsrVisualization } from '../../Composables/useAsrVisualization';
import { useStepAssessment } from '../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';
import { RESULT_TONE_ASSESSMENT, letterPairDisplay, resultColorForTone } from '../../utils/assessmentDisplay';
import { vivianAsrReceivedCueForItem } from '../../utils/vivianAsrVoiceLines';

const props = defineProps({
    items: { type: Array, default: () => [] },
    initialIndex: { type: Number, default: 0 },
    assessmentAttemptId: { type: Number, required: true },
    assessmentMode: { type: Object, default: () => ({}) },
    submitUrl: { type: String, required: true },
    initialAgentMessage: { type: String, default: 'Say the letter out loud when you are ready. Use a clear voice, and take your time before you record.' },
    submitErrorMessage: { type: String, default: 'We could not check these answers yet. Please review the letters and try again.' },
    continueMessages: {
        type: Array,
        default: () => ["Thank you. Let's continue to the next item when you are ready.", "Good effort. Let's go to the next one and keep doing our best."],
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
const asrVisualizationPending = reactive({});
const { enabled: asrVisualizationEnabled } = useAsrVisualization();
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
const initialAgentText = String(props.initialAgentMessage ?? '').toLowerCase();
const initialAgentLineKey = initialAgentText.includes('final') || initialAgentText.includes('sound out loud')
    ? 'vivian.instruction.listen_then_say_sound'
    : 'vivian.task1.normal_start';
const agentLineKey = ref(initialAgentLineKey);
const agentIntent = ref('focused_instruction');
const agentState = ref('listening');
const agentSpeaking = ref(false);
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const isCurrentChecked = computed(() => Boolean(checkedItems[step.currentItem.value?.id]) && hasAnswerOrAudio(step.currentItem.value));
const currentTranscript = computed(() => String(generatedTranscripts[step.currentItem.value?.id] ?? '').trim());
const currentAsrVisualizationPending = computed(() => Boolean(asrVisualizationPending[step.currentItem.value?.id]));
const currentLetterDisplay = computed(() => {
    const item = step.currentItem.value;

    return letterPairDisplay(
        item?.payload?.expected_answer,
        item?.payload?.target_letter,
        item?.payload?.letter,
        item?.prompt,
    );
});
const currentDisplayState = computed(() => {
    if (asrVisualizationEnabled.value && (isCurrentUploading.value || (currentAsrVisualizationPending.value && isCurrentChecked.value))) return 'processing';
    if (isCurrentChecked.value) return 'result';

    return 'item';
});
const currentResultTone = computed(() => RESULT_TONE_ASSESSMENT);
const currentResultColor = computed(() => resultColorForTone(currentResultTone.value));
const canSubmitCurrent = computed(() => {
    const item = step.currentItem.value;

    if (!item) return false;

    return Boolean(audioFiles[item.id]) || manualAnswerFor(item).length > 0;
});
const primaryLabel = computed(() => isCurrentChecked.value ? 'Next' : 'Submit');
const primaryDisabled = computed(() => form.processing || isCurrentUploading.value || currentAsrVisualizationPending.value || (!isCurrentChecked.value && !canSubmitCurrent.value));
const continueLineKeyForIndex = (index) => (index % 2 === 0 ? 'vivian.continue.thank_you' : 'vivian.continue.good_effort');
const setAgentPrompt = (message, state = 'speaking', lineKey = '', intent = 'focused_instruction') => {
    agentMessage.value = message;
    agentState.value = state;
    agentLineKey.value = lineKey;
    agentIntent.value = intent;
};
const setVivianAsrReceivedPrompt = (item) => {
    const cue = vivianAsrReceivedCueForItem(item, props.items);

    setAgentPrompt(cue.text, 'speaking', cue.lineKey, 'friendly_encouragement');
};
const clearAsrVisualizationPending = (item) => {
    if (item?.id) {
        delete asrVisualizationPending[item.id];
    }
};
const markAsrVisualizationPending = (item) => {
    if (item?.id && asrVisualizationEnabled.value) {
        asrVisualizationPending[item.id] = true;
    }
};
const clearAllAsrVisualizationPending = () => {
    Object.keys(asrVisualizationPending).forEach((key) => delete asrVisualizationPending[key]);
};

watch(asrVisualizationEnabled, (enabled) => {
    if (!enabled) {
        clearAllAsrVisualizationPending();
    }
});

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
    delete checkedItems[item.id];
    clearAsrVisualizationPending(item);
    setAgentPrompt('Take your time before you answer. Listen first, then choose or say the response clearly.', 'speaking', 'vivian.instruction.listen_choose_or_say');
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
    clearAsrVisualizationPending(item);
};

const setAnswer = (item, value) => {
    step.answers[item.id] = value;
};

const uploadAudio = async (item, file) => {
    if (uploading[item.id] || form.processing) {
        return false;
    }

    uploading[item.id] = true;
    markAsrVisualizationPending(item);
    uploadErrors[item.id] = '';
    setAgentPrompt('I am checking your recording now. Please wait a moment while I listen carefully.', 'thinking', 'vivian.processing.checking_recording');

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
            setVivianAsrReceivedPrompt(item);
            return true;
        }

        delete checkedItems[item.id];
        clearAsrVisualizationPending(item);
        uploadErrors[item.id] = asr.message;
        setAgentPrompt("Something went wrong while checking your recording. That's okay, please try again with a clear voice.", 'retry', 'vivian.error.recording_check_failed', 'gentle_reassurance');
        return false;
    } catch (error) {
        delete checkedItems[item.id];
        clearAsrVisualizationPending(item);
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        setAgentPrompt("Something went wrong while checking your recording. That's okay, please try again with a clear voice.", 'retry', 'vivian.error.recording_check_failed', 'gentle_reassurance');
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
        clearAsrVisualizationPending(item);
        setAgentPrompt(`You said: ${manualAnswer}`, 'speaking', '');
        return;
    }

    const file = audioFiles[item.id];
    if (!file) {
        setAgentPrompt('Say the letter out loud when you are ready. Use a clear voice, and take your time before you record.', 'speaking', 'vivian.task1.normal_start');
        return;
    }

    await uploadAudio(item, file);
};

const submit = () => {
    if (!step.validateComplete()) {
        setAgentPrompt('Say the letter out loud when you are ready. Use a clear voice, and take your time before you record.', 'speaking', 'vivian.task1.normal_start');
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
            setAgentPrompt("Something went wrong while checking your recording. That's okay, please try again with a clear voice.", 'retry', 'vivian.error.recording_check_failed', 'gentle_reassurance');
        },
    });
};

const goNextOrFinish = () => {
    if (!isCurrentChecked.value) {
        setAgentPrompt('I am checking your answer now. Please wait while I review it.', 'speaking', 'vivian.processing.checking_answer');
        return;
    }

    if (!step.validateCurrent()) {
        setAgentPrompt('Say the letter out loud when you are ready. Use a clear voice, and take your time before you record.', 'speaking', 'vivian.task1.normal_start');
        return;
    }

    setAgentPrompt(
        props.continueMessages[step.currentIndex.value % props.continueMessages.length] ?? 'Thank you. Let us continue.',
        'speaking',
        continueLineKeyForIndex(step.currentIndex.value),
        'friendly_encouragement',
    );

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
            :agent-intent="agentIntent"
            :agent-line-key="agentLineKey"
            :progress="step.progressPercent.value"
            :primary-label="primaryLabel"
            :primary-disabled="primaryDisabled"
            :display-state="currentDisplayState"
            @primary="handlePrimary"
            @agent-speaking-change="setAgentSpeaking"
        >
            <template #prompt>
                <div :key="step.currentItem.value.id" class="letter-prompt">
                    {{ currentLetterDisplay || step.currentItem.value.prompt }}
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

            <template #processing>
                <AsrTranscriptVisualizer
                    :transcript="currentTranscript"
                    :expected-text="step.currentItem.value.payload?.expected_answer ?? step.currentItem.value.prompt"
                    :asr-result="asrResults[step.currentItem.value.id]"
                    :is-processing="isCurrentUploading"
                    :error="uploadErrors[step.currentItem.value.id] ?? ''"
                    placeholder="Transcript will appear here"
                    box-class="min-h-0 h-full w-full flex-1 resize-none overflow-y-auto rounded-lg border border-slate-200 bg-white p-4 text-2xl font-black leading-tight text-slate-800 transition placeholder:text-slate-300 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                    @sequence-started="markAsrVisualizationPending(step.currentItem.value)"
                    @sequence-finished="clearAsrVisualizationPending(step.currentItem.value)"
                />
            </template>

            <template #result>
                <div
                    :key="`${step.currentItem.value.id}-${currentTranscript}`"
                    class="letter-prompt letter-result"
                    :class="`letter-result--${currentResultTone}`"
                    :style="{ color: currentResultColor }"
                >
                    {{ currentTranscript }}
                </div>
            </template>

            <template #status>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="rounded-lg bg-rose-50 px-3 py-2 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">
                    {{ uploadErrors[step.currentItem.value.id] }}
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
    max-width: 100%;
    place-items: center;
    overflow-wrap: anywhere;
    font-size: clamp(6rem, min(70cqh, 18cqw), 11rem);
    font-weight: 900;
    line-height: 0.9;
    color: #000000;
    text-shadow: 0 3px 0 rgba(255, 255, 255, 0.8), 0 6px 14px rgba(54, 83, 101, 0.18);
}

.letter-result--result-correct {
    color: var(--rd-result-correct, #4c563f);
}

.letter-result--result-wrong {
    color: var(--rd-result-wrong, #692721);
}
</style>
