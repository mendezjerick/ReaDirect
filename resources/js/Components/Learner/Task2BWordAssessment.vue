<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from './AssessmentTaskWorkspace.vue';
import AssessmentPromptText from './AssessmentPromptText.vue';
import AudioRecorder from './AudioRecorder.vue';
import AsrTranscriptVisualizer from './AsrTranscriptVisualizer.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';
import { getWordImage } from '../../utils/readingIllustrations';

const props = defineProps({
    items: { type: Array, default: () => [] },
    initialIndex: { type: Number, default: 0 },
    assessmentAttemptId: { type: Number, required: true },
    assessmentMode: { type: Object, default: () => ({}) },
    submitUrl: { type: String, required: true },
    initialAgentMessage: { type: String, default: 'Read the highlighted word in the sentence. When you are ready, record it clearly.' },
    submitErrorMessage: { type: String, default: 'We could not check these sentences yet. Please review them and try again.' },
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
const hasManualOverride = (item) => canUseManualFallback.value && manualAnswerFor(item).length > 0;
const spokenLetterAliases = {
    a: ['a', 'aye', 'ay'],
    b: ['be', 'bee'],
    c: ['see', 'sea'],
    i: ['i', 'eye'],
    o: ['o', 'oh'],
    q: ['cue', 'queue'],
    r: ['are'],
    u: ['you', 'yew'],
    x: ['ex'],
    y: ['why'],
};
const normalizeText = (value) => String(value ?? '').trim().toLowerCase().replace(/[^\w\s]/g, '').replace(/\s+/g, ' ');
const isSpokenLetterAliasForExpected = (answer, expected) => {
    const normalizedAnswer = normalizeText(answer);
    const normalizedExpected = normalizeText(expected);

    return normalizedAnswer.length === 1 && (spokenLetterAliases[normalizedAnswer] ?? []).includes(normalizedExpected);
};
const hasUsableTranscript = (item, answer) => {
    const expectedPrompt = String(item?.payload?.target_word ?? item?.payload?.expected_answer ?? item?.prompt ?? '').trim();
    const manualAnswer = String(answer ?? '').trim();
    const normalizedAnswer = manualAnswer || answerFor(item);

    if (!normalizedAnswer) return false;
    if (/^\d+$/.test(normalizedAnswer)) return false;
    if (uploadedAudioIds[item?.id]) return true;
    if (!expectedPrompt) return normalizedAnswer.length > 0;
    if (isSpokenLetterAliasForExpected(normalizedAnswer, expectedPrompt)) return true;

    return normalizedAnswer.length >= Math.max(2, Math.floor(expectedPrompt.length * 0.6));
};
const hasAnswerOrAudio = (item, answer) => (Boolean(uploadedAudioIds[item?.id]) || hasManualOverride(item)) && hasUsableTranscript(item, answer);
const step = useStepAssessment(props.items, { emptyMessage: 'Almost there! Finish this item to continue.', initialIndex: props.initialIndex ?? 0, isAnswered: hasAnswerOrAudio });
const agentMessage = ref(props.initialAgentMessage);
const agentLineKey = ref('vivian.task2b.word_sentence_start');
const agentIntent = ref('focused_instruction');
const agentState = ref('listening');
const agentSpeaking = ref(false);
const neutralMessages = ["Thank you. Let's continue to the next item when you are ready.", "Good effort. Let's go to the next one and keep doing our best."];
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const currentTranscript = computed(() => String(generatedTranscripts[step.currentItem.value?.id] ?? '').trim());
const currentWordImage = computed(() => getWordImage(step.currentItem.value?.payload?.target_word));
const isCurrentChecked = computed(() => Boolean(checkedItems[step.currentItem.value?.id]) && hasAnswerOrAudio(step.currentItem.value, answerFor(step.currentItem.value)));
const canSubmitCurrent = computed(() => {
    const item = step.currentItem.value;

    if (!item) return false;

    return Boolean(audioFiles[item.id]) || hasManualOverride(item);
});
const primaryLabel = computed(() => isCurrentChecked.value ? 'Next' : 'Submit');
const primaryDisabled = computed(() => form.processing || isCurrentUploading.value || (!isCurrentChecked.value && !canSubmitCurrent.value));
const continueLineKeyForIndex = (index) => (index % 2 === 0 ? 'vivian.continue.thank_you' : 'vivian.continue.good_effort');
const setAgentPrompt = (message, state = 'speaking', lineKey = '', intent = 'focused_instruction') => {
    agentMessage.value = message;
    agentState.value = state;
    agentLineKey.value = lineKey;
    agentIntent.value = intent;
};

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
    delete checkedItems[item.id];
    step.feedback.value = '';
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
};

const setAnswer = (item, value) => {
    step.answers[item.id] = value;
    delete checkedItems[item.id];
};

const uploadAudio = async (item, file) => {
    if (uploading[item.id] || form.processing) {
        return false;
    }

    uploading[item.id] = true;
    uploadErrors[item.id] = '';
    setAgentPrompt('I am checking your recording now. Please wait a moment while I listen carefully.', 'thinking', 'vivian.processing.checking_recording');

    try {
        const payload = new FormData();
        payload.append('audio', file);
        payload.append('context_type', 'assessment_task');
        payload.append('assessment_attempt_id', String(props.assessmentAttemptId));
        payload.append('item_id', String(item.id));
        payload.append('task_type', 'crla_task_2b_sentence');
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
            step.feedback.value = '';
            setAgentPrompt(`You said: ${transcript}`, 'speaking', '');
            return true;
        }

        delete checkedItems[item.id];
        uploadErrors[item.id] = asr.message;
        setAgentPrompt("Something went wrong while checking your recording. That's okay, please try again with a clear voice.", 'retry', 'vivian.error.recording_check_failed', 'gentle_reassurance');
        return false;
    } catch (error) {
        delete checkedItems[item.id];
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
        step.feedback.value = '';
        setAgentPrompt(`You said: ${manualAnswer}`, 'speaking', '');
        return;
    }

    const file = audioFiles[item.id];
    if (!file) {
        setAgentPrompt('Read the highlighted word in the sentence. When you are ready, record it clearly.', 'speaking', 'vivian.task2b.word_sentence_start');
        return;
    }

    await uploadAudio(item, file);
};

const submit = () => {
    if (!step.validateComplete()) {
        setAgentPrompt('Read the highlighted word in the sentence. When you are ready, record it clearly.', 'speaking', 'vivian.task2b.word_sentence_start');
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
        setAgentPrompt('Read the highlighted word in the sentence. When you are ready, record it clearly.', 'speaking', 'vivian.task2b.word_sentence_start');
        return;
    }

    setAgentPrompt(
        neutralMessages[step.currentIndex.value % neutralMessages.length],
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
            :prompt-image="currentWordImage"
            @primary="handlePrimary"
            @agent-speaking-change="setAgentSpeaking"
        >
            <template #prompt>
                <AssessmentPromptText
                    :key="step.currentItem.value.id"
                    label="Read the highlighted word"
                    :prompt="step.currentItem.value.prompt"
                    :highlight-targets="[{ text: step.currentItem.value.payload?.target_word ?? '', wholeWord: false }]"
                    size="sentence"
                />
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
                    label="Word voice"
                    prompt-type="word"
                    @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                    @submit="(file) => uploadAudio(step.currentItem.value, file)"
                    @cleared="() => clearAudio(step.currentItem.value)"
                />
            </template>

            <template #transcript>
                <AsrTranscriptVisualizer
                    :transcript="currentTranscript"
                    :expected-text="step.currentItem.value.payload?.target_word ?? step.currentItem.value.payload?.expected_answer ?? step.currentItem.value.prompt"
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
