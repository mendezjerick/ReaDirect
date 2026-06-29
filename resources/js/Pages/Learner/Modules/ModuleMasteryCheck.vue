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
import { useAsrVisualization } from '../../../Composables/useAsrVisualization';
import { useStepAssessment } from '../../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';
import { acceptedFromRetryState, latestRetryAttempt, letterPairDisplay, resultToneForAccepted } from '../../../utils/assessmentDisplay';
import { AUTOMATIC_CIEL_LISTENING_MODE, AUTOMATIC_CIEL_LISTENING_STATES } from '../../../Composables/useAutomaticCielListeningSession';
import { highlightTargetsForModuleItem } from '../../../utils/modulePromptHighlight';
import { getWordImage } from '../../../utils/readingIllustrations';

const props = defineProps({ module: Object, moduleAttemptId: Number, items: Array, assessmentMode: Object, listeningMode: Object });
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
const asrVisualizationPending = reactive({});
const { enabled: asrVisualizationEnabled } = useAsrVisualization();
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
    if (props.module?.key === 'module_1') return 'letter';
    if (props.module?.key === 'module_3') return 'sentence';

    return 'word';
});
const initialModuleCues = {
    letter: [
        { text: 'Look at the letter below and say its sound clearly.', lineKey: 'ciel.module1.before_recording.letter_01' },
        { text: 'Say the displayed letter sound slowly.', lineKey: 'ciel.module1.before_recording.letter_02' },
        { text: 'Read the letter sound below carefully.', lineKey: 'ciel.module1.before_recording.letter_03' },
    ],
    word: [
        { text: 'Read the displayed word below carefully.', lineKey: 'ciel.module2.before_recording.word_01' },
        { text: 'Read the word below slowly.', lineKey: 'ciel.module2.before_recording.word_02' },
        { text: 'Look at the word below and say it clearly.', lineKey: 'ciel.module2.before_recording.word_03' },
    ],
    sentence: [
        { text: 'Read the sentence below clearly and naturally.', lineKey: 'ciel.module3.before_recording.sentence_01' },
        { text: 'Read the displayed sentence at a steady pace.', lineKey: 'ciel.module3.before_recording.sentence_02' },
        { text: 'Read the sentence below carefully from start to finish.', lineKey: 'ciel.module3.before_recording.sentence_03' },
    ],
};
const afterRecordingCues = [
    { text: 'Listen to your recording, then click Submit when you are ready.', lineKey: 'ciel.module.after_recording.review_submit_01' },
    { text: 'You can review your audio first, then press Submit.', lineKey: 'ciel.module.after_recording.review_submit_02' },
    { text: 'Play your recording if you want to check it, then submit your answer.', lineKey: 'ciel.module.after_recording.review_submit_03' },
];
const noRecordingCues = {
    letter: { text: 'Please record the letter sound first.', lineKey: 'ciel.module1.validation.record_letter_first' },
    word: { text: 'Please record the word first.', lineKey: 'ciel.module2.validation.record_word_first' },
    sentence: { text: 'Please record the sentence first.', lineKey: 'ciel.module3.validation.record_sentence_first' },
};
const praiseCues = [
    { text: "Nice work! You said that clearly, and I can hear that you're getting more confident.", lineKey: 'ciel.praise.clear_confident' },
    { text: 'Great job! You got that one, and you read it with a nice clear voice.', lineKey: 'ciel.praise.got_that_one' },
    { text: "Good job, that was clear. Let's keep going while you're doing so well.", lineKey: 'ciel.praise.keep_going_clear' },
];
const moduleCueTypeFor = (item = null) => {
    const moduleKey = String(item?.payload?.module_key ?? props.module?.key ?? '');
    const activity = String(item?.activity_type ?? '');

    if (moduleKey === 'module_1') return 'letter';
    if (moduleKey === 'module_3' || activity.includes('sentence') || activity.includes('paragraph')) return 'sentence';

    return 'word';
};
const cycleIndexForItem = (item = null) => {
    const directSequence = Number(item?.sequence);
    const listIndex = (props.items ?? []).findIndex((candidate) => candidate?.id === item?.id);
    const sequence = Number.isFinite(directSequence) && directSequence > 0
        ? directSequence
        : Math.max(listIndex + 1, 1);

    return (sequence - 1) % 3;
};
const cycleCueForItem = (cues, item = null) => cues[cycleIndexForItem(item)] ?? cues[0];
const initialCueForItem = (item = null) => cycleCueForItem(initialModuleCues[moduleCueTypeFor(item)] ?? initialModuleCues.word, item);
const afterRecordingCueForItem = (item = null) => cycleCueForItem(afterRecordingCues, item);
const noRecordingCueForItem = (item = null) => noRecordingCues[moduleCueTypeFor(item)] ?? noRecordingCues.word;
const praiseCueForItem = (item = null) => cycleCueForItem(praiseCues, item);
const correctEchoForItem = (item = null) => {
    const echo = item?.echo?.correct;

    return echo?.text && echo?.line_key ? echo : null;
};
const focusEchoStepsForItem = (item = null, fallbackText = '', fallbackAction = 'talk') => {
    const echo = correctEchoForItem(item);

    if (!echo) {
        return [{ text: fallbackText || 'Listen carefully, then try it again.', action: fallbackAction || 'talk' }];
    }

    return [
        { text: "I'll say it first, listen carefully.", action: 'talk', line_key: 'ciel.focus.echo_intro', intent: 'focused_instruction' },
        { text: echo.text, action: 'talk', line_key: echo.line_key, intent: echo.intent ?? 'module_echo_correct' },
        { text: "I'll repeat it one more time, listen closely.", action: 'talk', line_key: 'ciel.focus.echo_repeat', intent: 'focused_instruction' },
        { text: echo.text, action: 'talk', line_key: echo.line_key, intent: echo.intent ?? 'module_echo_correct' },
    ];
};
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
const agentMessage = ref("This is your mini mastery check. Do your best one item at a time, and I'll stay with you.");
const agentLineKey = ref('ciel.mastery.start');
const agentIntent = ref('friendly_encouragement');
const agentState = ref('speaking');
const returningToDashboard = ref(false);
const cielFocusEvent = ref(null);
const focusModeVisible = computed(() => cielFocusEvent.value?.enabled === true);
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const isCurrentChecking = computed(() => Boolean(checking[step.currentItem.value?.id]));
const currentHasSubmittedAudio = computed(() => Boolean(uploadedAudioIds[step.currentItem.value?.id]) && !uploadErrors[step.currentItem.value?.id]);
const currentHasSubmittedManual = computed(() => Boolean(submittedManualItems[step.currentItem.value?.id]) && manualAnswerFor(step.currentItem.value).length > 0);
const currentHasSubmittedAnswer = computed(() => currentHasSubmittedAudio.value || currentHasSubmittedManual.value);
const currentTranscript = computed(() => {
    const item = step.currentItem.value;

    return String(generatedTranscripts[item?.id] ?? latestRetryAttempt(retryStates[item?.id])?.answer ?? '').trim();
});
const promptDisplayText = computed(() => {
    const item = step.currentItem.value;

    if (promptDisplaySize.value === 'letter') {
        return letterPairDisplay(
            item?.payload?.expected_answer,
            item?.payload?.target_letter,
            item?.payload?.letter,
            item?.display_prompt,
            item?.prompt,
        ) || String(item?.display_prompt ?? item?.prompt ?? '');
    }

    return String(item?.display_prompt ?? item?.prompt ?? '');
});
const promptDisplayLabel = computed(() => (promptDisplaySize.value === 'letter' ? '' : 'Mini Mastery Check'));
const currentHighlightTargets = computed(() => highlightTargetsForModuleItem(step.currentItem.value));
const currentWordImage = computed(() => {
    const item = step.currentItem.value;
    const word = String(item?.payload?.expected_answer ?? item?.prompt ?? '').trim().split(/\s+/)[0];

    return getWordImage(word);
});
const currentRetryState = computed(() => retryStates[step.currentItem.value?.id] ?? defaultRetryState());
const isCurrentResolved = computed(() => currentRetryState.value.is_resolved === true);
const currentHasCheckResult = computed(() => Number(currentRetryState.value.attempt_count ?? 0) > 0 || isCurrentResolved.value);
const currentAsrVisualizationPending = computed(() => Boolean(asrVisualizationPending[step.currentItem.value?.id]));
const currentDisplayState = computed(() => {
    if (asrVisualizationEnabled.value && (isCurrentUploading.value || (currentAsrVisualizationPending.value && currentTranscript.value && currentHasSubmittedAnswer.value))) return 'processing';
    if (currentTranscript.value && (currentHasSubmittedAnswer.value || currentRetryState.value.attempt_count > 0 || isCurrentResolved.value)) return 'result';

    return 'item';
});
const currentResultAccepted = computed(() => {
    if (!currentHasCheckResult.value) return null;

    return acceptedFromRetryState(currentRetryState.value);
});
const currentResultTone = computed(() => (currentHasCheckResult.value ? resultToneForAccepted(currentResultAccepted.value) : 'item'));
const currentAttemptSlots = computed(() => Array.from({ length: currentRetryState.value.max_attempts ?? 3 }, (_, index) => {
    const attemptNumber = index + 1;
    const attempt = currentRetryState.value.attempts?.find((entry) => Number(entry.attempt) === attemptNumber);

    return {
        attempt: attemptNumber,
        status: attempt?.status ?? 'unused',
    };
}));
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

    return step.isLast.value ? 'Finish check' : 'Next';
});
const canSubmitCurrent = computed(() => {
    const item = step.currentItem.value;

    if (!item) return false;

    return Boolean(audioFiles[item.id]) || manualAnswerFor(item).length > 0 || currentHasSubmittedAnswer.value;
});
const primaryDisabled = computed(() => (
    form.processing
    || isCurrentUploading.value
    || currentAsrVisualizationPending.value
    || isCurrentChecking.value
    || focusModeVisible.value
    || (!isCurrentResolved.value && !currentHasSubmittedAnswer.value && !canSubmitCurrent.value)
));
const setAgentPrompt = (message, state = 'speaking', lineKey = '', intent = 'focused_instruction') => {
    agentMessage.value = message;
    agentState.value = state;
    agentLineKey.value = lineKey;
    agentIntent.value = intent;
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
const setInitialAgentPrompt = (item = step.currentItem.value) => {
    const cue = initialCueForItem(item);
    setAgentPrompt(cue.text, 'speaking', cue.lineKey);
};
const setNoRecordingPrompt = (item = step.currentItem.value) => {
    const cue = noRecordingCueForItem(item);
    setAgentPrompt(cue.text, 'encouraging', cue.lineKey);
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
        clearAllAsrVisualizationPending();
        seedRetryStates(props.items);
        manualRecorderOverride.value = false;
        automaticListeningPanel.value?.stopSession?.();
        setAgentPrompt("This is your mini mastery check. Do your best one item at a time, and I'll stay with you.", 'speaking', 'ciel.mastery.start', 'friendly_encouragement');
        cielFocusEvent.value = null;
        form.clearErrors();
    }
);

watch(asrVisualizationEnabled, (enabled) => {
    if (!enabled) {
        clearAllAsrVisualizationPending();
    }
});

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
    clearAsrVisualizationPending(item);
    const cue = afterRecordingCueForItem(item);
    setAgentPrompt(cue.text, 'speaking', cue.lineKey);
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
    clearAsrVisualizationPending(item);
    recorderResetKeys[item.id] = (recorderResetKeys[item.id] ?? 0) + 1;
    if (resetAgent) {
        setInitialAgentPrompt(item);
    }
};

const handleRecorderError = (message) => {
    setAgentPrompt('I could not hear that clearly. That is okay, so please try again with your clear reading voice.', 'error', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    markAsrVisualizationPending(item);
    setAgentPrompt('I am checking your reading now. Please wait a moment while I listen carefully.', 'thinking', 'ciel.module.processing.checking_reading');

    try {
        const payload = new FormData();
        payload.append('audio', file);
        payload.append('context_type', 'module_activity');
        payload.append('module_attempt_id', String(props.moduleAttemptId));
        payload.append('item_id', String(item.id));
        payload.append('activity_type', 'mastery_check');
        payload.append('task_type', 'module_mastery');
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
            setAgentPrompt("I heard your answer clearly. Let's check it together and keep going.", 'speaking', 'ciel.asr.success_generic', 'friendly_encouragement');
            return true;
        }

        uploadErrors[item.id] = asr.message;
        clearAsrVisualizationPending(item);
        setAgentPrompt('I could not hear that clearly. That is okay, so please try again with your clear reading voice.', 'unclear', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
        return false;
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        clearAsrVisualizationPending(item);
        setAgentPrompt('I could not hear that clearly. That is okay, so please try again with your clear reading voice.', 'error', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
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
        setNoRecordingPrompt(item);
        step.feedback.value = 'Record this item before checking.';
        return false;
    }

    checking[item.id] = true;
    step.feedback.value = '';
    setAgentPrompt('I am checking your reading now. Please wait a moment while I listen carefully.', 'checking', 'ciel.module.processing.checking_reading');

    try {
        const response = await fetch(`/learner/modules/${props.module.key}/mastery-check/check`, {
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
            const praiseCue = praiseCueForItem(item);
            setAgentPrompt(
                praiseCue.text,
                cielAgent?.animation ?? agentCue?.action ?? (step.isLast.value ? 'section_complete' : 'correct'),
                praiseCue.lineKey,
                'happy_praise',
            );
        } else if (retryStates[item.id].can_retry) {
            setAgentPrompt(
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
            setAgentPrompt(
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
                target_type: item.echo?.target_type ?? moduleCueTypeFor(item),
                target_text: item.echo?.target_text ?? cielAgent.display_target,
                reason: cielAgent.teaching_focus ?? 'agent_focus_teach',
                reward: null,
                dialogue_steps: focusEchoStepsForItem(item, cielAgent.message, cielAgent.animation),
            };
        } else if (result.ciel_focus_event?.enabled) {
            cielFocusEvent.value = result.ciel_focus_event;
        }

        return retryStates[item.id].is_resolved;
    } catch (error) {
        step.feedback.value = error.message || 'We could not check this item yet.';
        setAgentPrompt('I could not hear that clearly. That is okay, so please try again with your clear reading voice.', 'error', 'ciel.module.audio_unclear.try_clear_voice', 'gentle_reassurance');
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
    setAgentPrompt('Ciel stopped listening safely. You can use Manual Recording Mode and keep practicing at your own pace.', 'error', 'ciel.automatic.stopped', 'gentle_reassurance');
};

const fallbackToManualRecorder = () => {
    automaticListeningPanel.value?.stopSession?.();
    manualRecorderOverride.value = true;
    setAgentPrompt("Ready? Let's read this one together. Go slowly, and just try your best.", 'speaking', 'ciel.friendly.ready_read_together', 'friendly_encouragement');
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
        clearAsrVisualizationPending(item);
        setAgentPrompt("I heard your answer clearly. Let's check it together and keep going.", 'speaking', 'ciel.asr.success_generic', 'friendly_encouragement');
        return true;
    }

    if (currentHasSubmittedAudio.value) {
        return true;
    }

    const file = audioFiles[item.id];
    if (!file) {
        setNoRecordingPrompt(item);
        step.feedback.value = 'Record this item before submitting.';
        return false;
    }

    return uploadAudio(item, file);
};

const submit = () => {
    form.post(`/learner/modules/${props.module.key}/mastery-check`, { forceFormData: true });
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
    setInitialAgentPrompt(step.currentItem.value);
    window.setTimeout(resumeAutomaticListeningIfReady, 300);
};

const returnToDashboard = () => {
    if (returningToDashboard.value || focusModeVisible.value) return;
    returningToDashboard.value = true;
    automaticListeningPanel.value?.stopSession?.();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
    setAgentPrompt('See you next time. Keep practicing, keep using your clear voice, and remember that every step helps.', 'happy', 'ciel.module.goodbye', 'playful_friend');
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
            :agent-state="agentState"
            :agent-message="agentMessage"
            :agent-intent="agentIntent"
            :agent-line-key="agentLineKey"
            :progress="step.progressPercent.value"
            :primary-label="primaryLabel"
            :primary-disabled="primaryDisabled"
            :prompt-image="currentWordImage"
            :display-state="currentDisplayState"
            @primary="handlePrimary"
            @agent-speaking-change="handleWorkspaceAgentSpeakingChange"
        >
            <template #prompt>
                <AssessmentPromptText
                    :key="step.currentItem.value.id"
                    :label="promptDisplayLabel"
                    :prompt="promptDisplayText"
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
                    label="Check voice"
                    @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                    @submit="(file) => uploadAudio(step.currentItem.value, file)"
                    @cleared="() => clearAudio(step.currentItem.value)"
                    @error="handleRecorderError"
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
                <AssessmentPromptText
                    :key="`${step.currentItem.value.id}-${currentTranscript}`"
                    :prompt="currentTranscript"
                    :size="promptDisplaySize"
                    :tone="currentResultTone"
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
