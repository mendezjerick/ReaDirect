<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from '../../../Components/Learner/AssessmentTaskWorkspace.vue';
import AssessmentPromptText from '../../../Components/Learner/AssessmentPromptText.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AsrTranscriptVisualizer from '../../../Components/Learner/AsrTranscriptVisualizer.vue';
import AutomaticCielListeningPanel from '../../../Components/Learner/AutomaticCielListeningPanel.vue';
import CielFocusMode from '../../../Components/Learner/CielFocusMode.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';
import { AUTOMATIC_CIEL_LISTENING_MODE, AUTOMATIC_CIEL_LISTENING_STATES } from '../../../Composables/useAutomaticCielListeningSession';
import { highlightTargetsForModuleItem } from '../../../utils/modulePromptHighlight';
import { getWordImage } from '../../../utils/readingIllustrations';

const props = defineProps({
    module: Object,
    moduleAttemptId: Number,
    activityType: String,
    activityLabel: String,
    items: Array,
    nextActivityType: String,
    assessmentMode: Object,
    listeningMode: Object,
});

const form = useForm({});
const audioFiles = reactive({});
const audioDurations = reactive({});
const uploadedAudioIds = reactive({});
const transcriptSources = reactive({});
const generatedTranscripts = reactive({});
const asrResults = reactive({});
const uploadErrors = reactive({});
const uploading = reactive({});
const checking = reactive({});
const retryStates = reactive({});
const recorderResetKeys = reactive({});
const submittedManualItems = reactive({});
const automaticListeningPanel = ref(null);
const manualRecorderOverride = ref(false);
const agentSpeaking = ref(false);
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isAutomaticListeningMode = computed(() => (
    props.listeningMode?.current === AUTOMATIC_CIEL_LISTENING_MODE
    && props.listeningMode?.automatic_mode_available === true
    && manualRecorderOverride.value !== true
));
const recorderPromptType = computed(() => {
    const activity = String(props.activityType ?? '');

    if (props.module?.key === 'module_1') return 'letter';
    if (props.module?.key === 'module_3') return 'sentence';
    if (activity.includes('sentence')) return 'sentence';
    if (activity.includes('rhyme')) return 'rhyme';
    if (activity.includes('letter')) return 'letter';

    return 'word';
});
const promptDisplaySize = computed(() => (recorderPromptType.value === 'letter'
    ? 'letter'
    : (recorderPromptType.value === 'sentence' ? 'sentence' : 'word')));
const manualAnswerFor = (item, answer = null) => canUseManualFallback.value ? String(answer ?? step.answers[item?.id] ?? '').trim() : '';
const answerFor = (item, answer = null) => manualAnswerFor(item, answer) || String(generatedTranscripts[item?.id] ?? '').trim();
const sourceFor = (item, answer = null) => manualAnswerFor(item, answer)
    ? 'manual'
    : (transcriptSources[item?.id] ?? (generatedTranscripts[item?.id] ? 'stt_auto' : 'stt_auto'));
const defaultRetryState = () => ({ max_attempts: 3, attempt_count: 0, remaining_attempts: 3, attempts: [], is_correct: false, is_resolved: false, can_retry: true, next_attempt: 1, feedback: null });
const seedRetryStates = (items) => {
    Object.keys(retryStates).forEach((key) => delete retryStates[key]);
    (items ?? []).forEach((item) => {
        retryStates[item.id] = item.retry_state ?? defaultRetryState();
    });
};
seedRetryStates(props.items);
const hasAnswerOrAudio = (item, answer) => answerFor(item, answer).length > 0 || Boolean(uploadedAudioIds[item?.id]) || Boolean(audioFiles[item?.id]) || Boolean(retryStates[item?.id]?.is_resolved);
const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.', isAnswered: hasAnswerOrAudio });
const coachMessage = ref('Read the prompt, then record your voice. I will help you practice.');
const coachLineKey = ref('ciel.instruction.look_listen_read');
const coachIntent = ref('focused_instruction');
const coachState = ref('speaking');
const returningToDashboard = ref(false);
const cielFocusEvent = ref(null);
const focusModeVisible = computed(() => cielFocusEvent.value?.enabled === true);
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const isCurrentChecking = computed(() => Boolean(checking[step.currentItem.value?.id]));
const currentHasSubmittedAudio = computed(() => Boolean(uploadedAudioIds[step.currentItem.value?.id]) && !uploadErrors[step.currentItem.value?.id]);
const currentHasSubmittedManual = computed(() => Boolean(submittedManualItems[step.currentItem.value?.id]) && manualAnswerFor(step.currentItem.value).length > 0);
const currentHasSubmittedAnswer = computed(() => currentHasSubmittedAudio.value || currentHasSubmittedManual.value);
const currentHighlightTargets = computed(() => highlightTargetsForModuleItem(step.currentItem.value));
const currentWordImage = computed(() => {
    const item = step.currentItem.value;
    const word = String(item?.payload?.expected_answer ?? item?.prompt ?? '').trim().split(/\s+/)[0];

    return getWordImage(word);
});
const currentRetryState = computed(() => retryStates[step.currentItem.value?.id] ?? defaultRetryState());
const currentAttemptSlots = computed(() => Array.from({ length: currentRetryState.value.max_attempts ?? 3 }, (_, index) => {
    const attemptNumber = index + 1;
    const attempt = currentRetryState.value.attempts?.find((entry) => Number(entry.attempt) === attemptNumber);

    return {
        attempt: attemptNumber,
        status: attempt?.status ?? 'unused',
    };
}));
const isCurrentResolved = computed(() => currentRetryState.value.is_resolved === true);
const automaticListeningDisabled = computed(() => (
    form.processing
    || isCurrentUploading.value
    || isCurrentChecking.value
    || focusModeVisible.value
    || agentSpeaking.value
    || isCurrentResolved.value
    || returningToDashboard.value
));
const primaryLabel = computed(() => {
    if (!isCurrentResolved.value) {
        return currentHasSubmittedAnswer.value ? 'Check' : 'Submit';
    }

    if (step.isLast.value) {
        return props.nextActivityType ? 'Finish activity' : 'Start mastery check';
    }

    return 'Next';
});
const canSubmitCurrent = computed(() => {
    const item = step.currentItem.value;

    if (!item) return false;

    return Boolean(audioFiles[item.id]) || manualAnswerFor(item).length > 0 || currentHasSubmittedAnswer.value;
});
const primaryDisabled = computed(() => (
    form.processing
    || isCurrentUploading.value
    || isCurrentChecking.value
    || focusModeVisible.value
    || (!isCurrentResolved.value && !currentHasSubmittedAnswer.value && !canSubmitCurrent.value)
));
const setCoachPrompt = (message, state = 'speaking', lineKey = '', intent = 'focused_instruction') => {
    coachMessage.value = message;
    coachState.value = state;
    coachLineKey.value = lineKey;
    coachIntent.value = intent;
};
watch(
    () => props.items.map((item) => item.id).join('|'),
    () => {
        step.reset(props.items);
        Object.keys(audioFiles).forEach((key) => delete audioFiles[key]);
        Object.keys(audioDurations).forEach((key) => delete audioDurations[key]);
        Object.keys(uploadedAudioIds).forEach((key) => delete uploadedAudioIds[key]);
        Object.keys(transcriptSources).forEach((key) => delete transcriptSources[key]);
        Object.keys(generatedTranscripts).forEach((key) => delete generatedTranscripts[key]);
        Object.keys(asrResults).forEach((key) => delete asrResults[key]);
        Object.keys(uploadErrors).forEach((key) => delete uploadErrors[key]);
        Object.keys(uploading).forEach((key) => delete uploading[key]);
        Object.keys(checking).forEach((key) => delete checking[key]);
        Object.keys(recorderResetKeys).forEach((key) => delete recorderResetKeys[key]);
        Object.keys(submittedManualItems).forEach((key) => delete submittedManualItems[key]);
        seedRetryStates(props.items);
        manualRecorderOverride.value = false;
        automaticListeningPanel.value?.stopSession?.();
        setCoachPrompt('Read the prompt, then record your voice. I will help you practice.', 'speaking', 'ciel.instruction.look_listen_read');
        cielFocusEvent.value = null;
        form.clearErrors();
    }
);

watch(
    () => props.listeningMode?.current,
    () => {
        manualRecorderOverride.value = false;
    },
);

watch(
    () => step.currentItem.value?.id,
    () => {
        if (isAutomaticListeningMode.value && automaticListeningPanel.value?.isActive?.value && !isCurrentResolved.value) {
            window.setTimeout(() => automaticListeningPanel.value?.resumeAfterCiel?.(), 250);
        }
    },
);

watch(focusModeVisible, (visible) => {
    if (!isAutomaticListeningMode.value) return;

    if (visible) {
        automaticListeningPanel.value?.pauseForTeaching?.();
        return;
    }

    window.setTimeout(() => {
        if (!isCurrentResolved.value) {
            automaticListeningPanel.value?.resumeAfterCiel?.();
        }
    }, 300);
});

const closeCielFocusMode = () => {
    cielFocusEvent.value = null;
    if (isAutomaticListeningMode.value && !isCurrentResolved.value) {
        window.setTimeout(() => automaticListeningPanel.value?.resumeAfterCiel?.(), 300);
    }
};

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
    delete submittedManualItems[item.id];
    setCoachPrompt('Listen to your answer. If you are happy with your answer, click Submit.', 'speaking', 'ciel.instruction.listen_then_say_word');
};

const clearAudio = (item, resetAgent = true) => {
    delete audioFiles[item.id];
    delete audioDurations[item.id];
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
    delete uploadErrors[item.id];
    delete uploading[item.id];
    delete submittedManualItems[item.id];
    recorderResetKeys[item.id] = (recorderResetKeys[item.id] ?? 0) + 1;
    if (resetAgent) {
        coachState.value = 'speaking';
    }
};

const handleRecorderError = (message) => {
    setCoachPrompt(message || 'We could not use that recording. Please try again.', 'error', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    setCoachPrompt('Checking your recording.', 'thinking', 'ciel.module.processing.checking_reading');

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
            step.feedback.value = '';
            setCoachPrompt(`You said: ${transcript}`, 'speaking', '');
            return true;
        }

        uploadErrors[item.id] = asr.message;
        setCoachPrompt(uploadErrors[item.id], 'unclear', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
        return false;
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        setCoachPrompt(uploadErrors[item.id], 'error', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
        return false;
    } finally {
        uploading[item.id] = false;
    }
};

const checkCurrent = async (automaticContext = null) => {
    const item = step.currentItem.value;

    if (!item || isCurrentResolved.value || isCurrentChecking.value || focusModeVisible.value) return false;

    const manualAnswer = manualAnswerFor(item);

    if (!manualAnswer && !uploadedAudioIds[item.id] && audioFiles[item.id]) {
        const uploaded = await uploadAudio(item, audioFiles[item.id]);
        if (!uploaded) return false;
    }

    if (!answerFor(item) && !uploadedAudioIds[item.id]) {
        setCoachPrompt('Let us answer this first.', 'encouraging', 'ciel.instruction.look_listen_read');
        step.feedback.value = 'Record this item before checking.';
        return false;
    }

    checking[item.id] = true;
    step.feedback.value = '';
    setCoachPrompt('Checking your answer.', 'checking', 'ciel.module.processing.checking_reading');

    try {
        const response = await fetch(`/learner/modules/${props.module.key}/activity/${props.activityType}/check`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                module_attempt_item_id: item.id,
                answer: answerFor(item),
                transcript_source: sourceFor(item),
                audio_file_id: uploadedAudioIds[item.id] ?? null,
                duration_seconds: audioDurations[item.id] ?? null,
                ...(automaticContext ? {
                    listening_mode: AUTOMATIC_CIEL_LISTENING_MODE,
                    automatic_session_id: automaticContext.automatic_session_id,
                    chunk_id: automaticContext.chunk_id,
                    session_mode: automaticContext.session_mode ?? AUTOMATIC_CIEL_LISTENING_MODE,
                    current_agent_state: automaticContext.current_agent_state ?? null,
                    silence_timeout: automaticContext.silence_timeout ?? false,
                } : {}),
            }),
        });
        const result = await response.json();

        if (!response.ok) {
            const message = result.message ?? Object.values(result.errors ?? {})?.[0]?.[0] ?? 'We could not check this item yet.';
            throw new Error(message);
        }

        retryStates[item.id] = result.retry_state ?? defaultRetryState();
        step.feedback.value = result.message ?? retryStates[item.id].feedback ?? '';
        const agentCue = result.agent_cue?.agent === 'ciel' ? result.agent_cue : null;
        const cielAgent = result.ciel_agent?.agent === 'ciel' ? result.ciel_agent : null;

        if (retryStates[item.id].is_correct) {
            setCoachPrompt(
                cielAgent?.message ?? agentCue?.message ?? 'That is correct. Go to the next one.',
                cielAgent?.animation ?? agentCue?.action ?? (step.isLast.value ? 'section_complete' : 'correct'),
                (cielAgent || agentCue) ? '' : 'ciel.praise.got_that_one',
                'happy_praise',
            );
        } else if (retryStates[item.id].can_retry) {
            setCoachPrompt(
                cielAgent?.message ?? agentCue?.message ?? 'Try this same item again.',
                cielAgent?.animation ?? agentCue?.action ?? (
                    Number(retryStates[item.id].attempt_count ?? 1) <= 1
                        ? 'incorrect'
                        : 'retry'
                ),
                (cielAgent || agentCue) ? '' : 'ciel.reassurance.try_one_more_time',
                'gentle_reassurance',
            );
            clearAudio(item, false);
            if (canUseManualFallback.value) {
                step.answers[item.id] = '';
            }
        } else {
            setCoachPrompt(
                cielAgent?.message ?? agentCue?.message ?? 'Good try. Go to the next one.',
                cielAgent?.animation ?? agentCue?.action ?? 'speaking',
                (cielAgent || agentCue) ? '' : 'ciel.reassurance.slow_down_together',
                'gentle_reassurance',
            );
        }

        if (cielAgent?.focus_mode?.enabled) {
            cielFocusEvent.value = {
                enabled: true,
                mode: 'teaching',
                target_type: props.module?.key === 'module_1' ? 'letter' : 'word',
                target_text: cielAgent.display_target,
                reason: cielAgent.teaching_focus ?? 'agent_focus_teach',
                reward: null,
                dialogue_steps: [
                    {
                        text: cielAgent.message,
                        action: cielAgent.animation,
                    },
                ],
            };
        } else if (result.ciel_focus_event?.enabled) {
            cielFocusEvent.value = result.ciel_focus_event;
        }

        return retryStates[item.id].is_resolved;
    } catch (error) {
        step.feedback.value = error.message || 'We could not check this item yet.';
        setCoachPrompt(step.feedback.value, 'error', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
        return false;
    } finally {
        checking[item.id] = false;
    }
};

const submitAutomaticChunk = async (context) => {
    const item = step.currentItem.value;

    if (!item || isCurrentResolved.value || focusModeVisible.value) {
        return { retry: true, message: 'Wait for Ciel, then read this item again.' };
    }

    const file = context.file;
    audioFiles[item.id] = file;
    audioDurations[item.id] = context.duration_seconds ?? file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];

    const uploaded = await uploadAudio(item, file);
    if (!uploaded) {
        return {
            retry: true,
            message: uploadErrors[item.id] || 'Try reading that again in a clear voice.',
        };
    }

    await checkCurrent(context);

    return {
        pause: true,
        state: focusModeVisible.value
            ? AUTOMATIC_CIEL_LISTENING_STATES.TEACHING_MODE
            : AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING,
    };
};

const handleAutomaticError = (message) => {
    const item = step.currentItem.value;
    if (item) {
        uploadErrors[item.id] = message;
    }
    setCoachPrompt(message || 'Ciel stopped listening safely. You can use Manual Recording Mode.', 'error', 'ciel.automatic.stopped', 'gentle_reassurance');
};

const fallbackToManualRecorder = () => {
    automaticListeningPanel.value?.stopSession?.();
    manualRecorderOverride.value = true;
    setCoachPrompt('Manual Recording Mode is ready.', 'speaking', 'ciel.friendly.ready_read_together', 'friendly_encouragement');
};

const resumeAutomaticListeningIfReady = () => {
    if (!isAutomaticListeningMode.value || focusModeVisible.value || agentSpeaking.value || isCurrentResolved.value || isCurrentChecking.value || isCurrentUploading.value) {
        return;
    }

    automaticListeningPanel.value?.resumeAfterCiel?.();
};

const handleAgentSpeakingStart = () => {
    agentSpeaking.value = true;
    automaticListeningPanel.value?.pauseForCiel?.();
};

const handleAgentSpeakingEnd = () => {
    agentSpeaking.value = false;
    window.setTimeout(resumeAutomaticListeningIfReady, 300);
};

const handleWorkspaceAgentSpeakingChange = (isSpeaking) => {
    if (isSpeaking) {
        handleAgentSpeakingStart();
        return;
    }

    handleAgentSpeakingEnd();
};

const submitCurrentForReview = async () => {
    const item = step.currentItem.value;

    if (!item || isCurrentUploading.value || isCurrentChecking.value || focusModeVisible.value) {
        return false;
    }

    const manualAnswer = manualAnswerFor(item);
    if (manualAnswer) {
        submittedManualItems[item.id] = true;
        generatedTranscripts[item.id] = manualAnswer;
        transcriptSources[item.id] = 'manual';
        uploadErrors[item.id] = '';
        step.feedback.value = '';
        setCoachPrompt(`You said: ${manualAnswer}`, 'speaking', '');
        return true;
    }

    if (currentHasSubmittedAudio.value) {
        return true;
    }

    const file = audioFiles[item.id];
    if (!file) {
        setCoachPrompt('Hold the orange button to record your answer first.', 'encouraging', 'ciel.instruction.say_sound_clearly');
        step.feedback.value = 'Record this item before submitting.';
        return false;
    }

    return uploadAudio(item, file);
};

const submit = () => {
    form.post(`/learner/modules/${props.module.key}/activity/${props.activityType}`, { forceFormData: true });
};

const handlePrimary = async () => {
    if (focusModeVisible.value) return;

    if (!isCurrentResolved.value) {
        if (!currentHasSubmittedAnswer.value) {
            await submitCurrentForReview();
            return;
        }

        await checkCurrent();
        return;
    }

    if (step.isLast.value) {
        automaticListeningPanel.value?.stopSession?.();
        submit();
        return;
    }

    step.goNext();
    setCoachPrompt('Read the prompt, then record your voice. I will help you practice.', 'speaking', 'ciel.instruction.look_listen_read');
    window.setTimeout(resumeAutomaticListeningIfReady, 300);
};

const returnToDashboard = () => {
    if (returningToDashboard.value || focusModeVisible.value) return;
    returningToDashboard.value = true;
    automaticListeningPanel.value?.stopSession?.();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
    setCoachPrompt('See you next time!', 'happy', 'ciel.module.goodbye', 'playful_friend');
    window.setTimeout(() => {
        window.location.href = '/learner/dashboard';
    }, 1200);
};

onBeforeUnmount(() => {
    automaticListeningPanel.value?.stopSession?.();
});
</script>

<template>
    <LearnerLayout assessment-task>
        <CielFocusMode
            :visible="focusModeVisible"
            :mode="cielFocusEvent?.mode ?? 'teaching'"
            :target-type="cielFocusEvent?.target_type"
            :target-text="cielFocusEvent?.target_text"
            :dialogue-steps="cielFocusEvent?.dialogue_steps ?? []"
            :reward="cielFocusEvent?.reward"
            @speaking-start="handleAgentSpeakingStart"
            @speaking-end="handleAgentSpeakingEnd"
            @closed="closeCielFocusMode"
        />

        <AssessmentTaskWorkspace
            agent-type="coach_feedback"
            :agent-state="coachState"
            :agent-message="coachMessage"
            :agent-intent="coachIntent"
            :agent-line-key="coachLineKey"
            :progress="step.progressPercent.value"
            :primary-label="primaryLabel"
            :primary-disabled="primaryDisabled"
            :prompt-image="currentWordImage"
            @primary="handlePrimary"
            @agent-speaking-change="handleWorkspaceAgentSpeakingChange"
        >
            <template #prompt>
                <AssessmentPromptText
                    :key="step.currentItem.value.id"
                    :label="activityLabel"
                    :prompt="step.currentItem.value.prompt"
                    :highlight-targets="currentHighlightTargets"
                    :size="promptDisplaySize"
                />
            </template>

            <template #recorder>
                <AutomaticCielListeningPanel
                    v-if="isAutomaticListeningMode"
                    ref="automaticListeningPanel"
                    :active-item="step.currentItem.value"
                    :disabled="automaticListeningDisabled"
                    :submit-chunk="submitAutomaticChunk"
                    @error="handleAutomaticError"
                    @fallback-manual="fallbackToManualRecorder"
                />
                <AudioRecorder
                    v-else
                    :key="step.currentItem.value.id"
                    :reset-key="`${step.currentItem.value.id}-${recorderResetKeys[step.currentItem.value.id] ?? 0}`"
                    presentation="hold-circle"
                    :max-duration-seconds="45"
                    :min-duration-seconds="0.5"
                    :prompt-type="recorderPromptType"
                    :require-review-before-submit="false"
                    :auto-transcribe-on-stop="false"
                    :submitting="isCurrentUploading || isCurrentChecking || focusModeVisible"
                    :submitted="currentHasSubmittedAudio"
                    :pulse-active="agentSpeaking"
                    :attempt-segments="currentAttemptSlots"
                    label="Practice voice"
                    @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                    @submit="(file) => uploadAudio(step.currentItem.value, file)"
                    @cleared="() => clearAudio(step.currentItem.value)"
                    @error="handleRecorderError"
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
            </template>

            <template v-if="canUseManualFallback" #qa>
                <label class="flex min-w-0 flex-1 items-center gap-2 text-xs font-black text-slate-500">
                    <span class="shrink-0">Developer QA: Manual Transcript Override</span>
                    <input
                        v-model="step.answers[step.currentItem.value.id]"
                        class="min-h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-800 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                        placeholder="Optional QA fallback text"
                    >
                </label>
            </template>
        </AssessmentTaskWorkspace>
    </LearnerLayout>
</template>
