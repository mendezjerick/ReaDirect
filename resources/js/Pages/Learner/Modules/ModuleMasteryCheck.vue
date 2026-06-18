<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AutomaticCielListeningPanel from '../../../Components/Learner/AutomaticCielListeningPanel.vue';
import CielFocusMode from '../../../Components/Learner/CielFocusMode.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import ModuleProgressBar from '../../../Components/ModuleProgressBar.vue';
import PromptCard from '../../../Components/PromptCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';
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
const uploadErrors = reactive({});
const uploading = reactive({});
const checking = reactive({});
const retryStates = reactive({});
const recorderResetKeys = reactive({});
const automaticListeningPanel = ref(null);
const manualRecorderOverride = ref(false);
const agentSpeaking = ref(false);
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);
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
const agentMessage = ref('This is your mini mastery check. Do your best one item at a time.');
const agentState = ref('speaking');
const returningToDashboard = ref(false);
const cielFocusEvent = ref(null);
const focusModeVisible = computed(() => cielFocusEvent.value?.enabled === true);
const progressLabel = computed(() => `Mastery ${step.currentIndex.value + 1} of ${props.items.length}`);
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const isCurrentChecking = computed(() => Boolean(checking[step.currentItem.value?.id]));
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
    if (!isCurrentResolved.value) return 'Check';

    return step.isLast.value ? 'Finish check' : 'Next';
});
const primaryDisabled = computed(() => form.processing || isCurrentUploading.value || isCurrentChecking.value || focusModeVisible.value);

watch(
    () => props.items.map((item) => item.id).join('|'),
    () => {
        step.reset(props.items);
        Object.keys(audioFiles).forEach((key) => delete audioFiles[key]);
        Object.keys(audioDurations).forEach((key) => delete audioDurations[key]);
        Object.keys(uploadedAudioIds).forEach((key) => delete uploadedAudioIds[key]);
        Object.keys(transcriptSources).forEach((key) => delete transcriptSources[key]);
        Object.keys(generatedTranscripts).forEach((key) => delete generatedTranscripts[key]);
        Object.keys(uploadErrors).forEach((key) => delete uploadErrors[key]);
        Object.keys(uploading).forEach((key) => delete uploading[key]);
        Object.keys(checking).forEach((key) => delete checking[key]);
        Object.keys(recorderResetKeys).forEach((key) => delete recorderResetKeys[key]);
        seedRetryStates(props.items);
        manualRecorderOverride.value = false;
        automaticListeningPanel.value?.stopSession?.();
        agentMessage.value = 'This is your mini mastery check. Do your best one item at a time.';
        agentState.value = 'speaking';
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
    agentMessage.value = 'Listen to your answer. If you are happy with your answer, click Submit.';
    agentState.value = 'speaking';
};

const clearAudio = (item, resetAgent = true) => {
    delete audioFiles[item.id];
    delete audioDurations[item.id];
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete uploadErrors[item.id];
    delete uploading[item.id];
    recorderResetKeys[item.id] = (recorderResetKeys[item.id] ?? 0) + 1;
    if (resetAgent) {
        agentState.value = 'speaking';
    }
};

const handleRecorderError = (message) => {
    agentMessage.value = message || 'We could not use that recording. Please try again.';
    agentState.value = 'error';
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    agentMessage.value = 'Checking your recording.';
    agentState.value = 'thinking';

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
            agentMessage.value = `You said: ${transcript}`;
            agentState.value = 'speaking';
            return true;
        }

        uploadErrors[item.id] = asr.message;
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'unclear';
        return false;
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'error';
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
        agentMessage.value = 'Let us answer this first.';
        agentState.value = 'encouraging';
        step.feedback.value = 'Record this item before checking.';
        return false;
    }

    checking[item.id] = true;
    step.feedback.value = '';
    agentMessage.value = 'Checking your answer.';
    agentState.value = 'checking';

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
            agentMessage.value = cielAgent?.message ?? agentCue?.message ?? 'That is correct. Go to the next one.';
            agentState.value = cielAgent?.animation ?? agentCue?.action ?? (step.isLast.value ? 'section_complete' : 'correct');
        } else if (retryStates[item.id].can_retry) {
            agentMessage.value = cielAgent?.message ?? agentCue?.message ?? 'Try this same item again.';
            agentState.value = cielAgent?.animation ?? agentCue?.action ?? (
                Number(retryStates[item.id].attempt_count ?? 1) <= 1
                    ? 'incorrect'
                    : 'retry'
            );
            clearAudio(item, false);
            if (canUseManualFallback.value) {
                step.answers[item.id] = '';
            }
        } else {
            agentMessage.value = cielAgent?.message ?? agentCue?.message ?? 'Good try. Go to the next one.';
            agentState.value = cielAgent?.animation ?? agentCue?.action ?? 'speaking';
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
        agentMessage.value = step.feedback.value;
        agentState.value = 'error';
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
    agentMessage.value = message || 'Ciel stopped listening safely. You can use Manual Recording Mode.';
    agentState.value = 'error';
};

const fallbackToManualRecorder = () => {
    automaticListeningPanel.value?.stopSession?.();
    manualRecorderOverride.value = true;
    agentMessage.value = 'Manual Recording Mode is ready.';
    agentState.value = 'speaking';
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

const submit = () => {
    form.post(`/learner/modules/${props.module.key}/mastery-check`, { forceFormData: true });
};

const handlePrimary = async () => {
    if (focusModeVisible.value) return;

    if (!isCurrentResolved.value) {
        await checkCurrent();
        return;
    }

    if (step.isLast.value) {
        automaticListeningPanel.value?.stopSession?.();
        submit();
        return;
    }

    step.goNext();
    agentMessage.value = 'This is your mini mastery check. Do your best one item at a time.';
    agentState.value = 'speaking';
    window.setTimeout(resumeAutomaticListeningIfReady, 300);
};

const returnToDashboard = () => {
    if (returningToDashboard.value || focusModeVisible.value) return;
    returningToDashboard.value = true;
    automaticListeningPanel.value?.stopSession?.();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
    agentMessage.value = 'See you next time!';
    agentState.value = 'happy';
    window.setTimeout(() => {
        window.location.href = '/learner/dashboard';
    }, 1200);
};

onBeforeUnmount(() => {
    automaticListeningPanel.value?.stopSession?.();
});
</script>

<template>
    <LearnerLayout :progress="90" backUrl="/learner/dashboard" backLabel="Back to Learner Dashboard">
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

        <template #agent>
            <AgentSpeakerPanel
                compact
                agent-type="coach_feedback"
                :state="agentState"
                :message="agentMessage"
                :tts-enabled="!focusModeVisible"
                @speaking-start="handleAgentSpeakingStart"
                @speaking-end="handleAgentSpeakingEnd"
            />
        </template>

        <section class="mx-auto grid max-w-xl gap-3 px-0.5">
            <div class="flex items-center justify-between">
                <StatusBadge status="Mini Mastery Check" variant="primary" />
                <StatusBadge :status="progressLabel" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard label="Check" :prompt="step.currentItem.value.prompt" :highlight-targets="currentHighlightTargets" :illustration="currentWordImage" size="word" />
            <div class="rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10">
                <div class="grid gap-3 lg:grid-cols-[220px_1fr] lg:items-center">
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
                        compact
                        :max-duration-seconds="45"
                        :prompt-type="recorderPromptType"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="isCurrentUploading || isCurrentChecking || focusModeVisible"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Check voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                        @error="handleRecorderError"
                    />
                    <div class="grid gap-3">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="slot in currentAttemptSlots"
                                :key="slot.attempt"
                                class="rounded-full px-3 py-1.5 text-xs font-black ring-1"
                                :class="{
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200': slot.status === 'correct',
                                    'bg-rose-50 text-rose-700 ring-rose-200': slot.status === 'incorrect',
                                    'bg-slate-50 text-slate-400 ring-slate-200': slot.status === 'unused',
                                }"
                            >
                                Attempt {{ slot.attempt }}<span v-if="slot.status === 'correct'">: Correct</span><span v-else-if="slot.status === 'incorrect'">: Try again</span>
                            </span>
                        </div>
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
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-end gap-3">
                <PrimaryButton :disabled="primaryDisabled" :class="{ 'opacity-70': primaryDisabled }" @click="handlePrimary">
                    {{ primaryLabel }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
