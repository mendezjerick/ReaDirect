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
import { ArrowRight, ArrowLeft } from 'lucide-vue-next';
import { useStepAssessment } from '../../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';
import { highlightTargetsForModuleItem } from '../../../utils/modulePromptHighlight';

const props = defineProps({
    module: Object,
    moduleAttemptId: Number,
    activityType: String,
    activityLabel: String,
    items: Array,
    nextActivityType: String,
    assessmentMode: Object,
});

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
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);
const recorderPromptType = computed(() => {
    const activity = String(props.activityType ?? '');

    if (props.module?.key === 'module_1') return 'letter';
    if (props.module?.key === 'module_3') return 'sentence';
    if (activity.includes('sentence')) return 'sentence';
    if (activity.includes('rhyme')) return 'rhyme';
    if (activity.includes('letter')) return 'letter';

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
const coachMessage = ref('Read the prompt, then record your voice. I will help you practice.');
const coachState = ref('speaking');
const returningToDashboard = ref(false);
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const isCurrentChecking = computed(() => Boolean(checking[step.currentItem.value?.id]));
const currentHighlightTargets = computed(() => highlightTargetsForModuleItem(step.currentItem.value));
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
const primaryLabel = computed(() => {
    if (!isCurrentResolved.value) return 'Check';

    if (step.isLast.value) {
        return props.nextActivityType ? 'Finish activity' : 'Start mastery check';
    }

    return 'Next';
});
const primaryDisabled = computed(() => form.processing || isCurrentUploading.value || isCurrentChecking.value);

const progressLabel = computed(() => `Activity ${step.currentIndex.value + 1} of ${props.items.length}`);

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
        coachMessage.value = 'Read the prompt, then record your voice. I will help you practice.';
        coachState.value = 'speaking';
        form.clearErrors();
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
        coachState.value = 'speaking';
    }
};

const handleRecorderError = (message) => {
    coachMessage.value = message || 'We could not use that recording. Please try again.';
    coachState.value = 'error';
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    coachMessage.value = 'Checking your recording.';
    coachState.value = 'thinking';

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
            coachMessage.value = `You said: ${transcript}`;
            coachState.value = 'speaking';
            return true;
        }

        uploadErrors[item.id] = asr.message;
        coachMessage.value = uploadErrors[item.id];
        coachState.value = 'unclear';
        return false;
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        coachMessage.value = uploadErrors[item.id];
        coachState.value = 'error';
        return false;
    } finally {
        uploading[item.id] = false;
    }
};

const checkCurrent = async () => {
    const item = step.currentItem.value;

    if (!item || isCurrentResolved.value || isCurrentChecking.value) return false;

    const manualAnswer = manualAnswerFor(item);

    if (!manualAnswer && !uploadedAudioIds[item.id] && audioFiles[item.id]) {
        const uploaded = await uploadAudio(item, audioFiles[item.id]);
        if (!uploaded) return false;
    }

    if (!answerFor(item) && !uploadedAudioIds[item.id]) {
        coachMessage.value = 'Let us answer this first.';
        coachState.value = 'encouraging';
        step.feedback.value = 'Record this item before checking.';
        return false;
    }

    checking[item.id] = true;
    step.feedback.value = '';
    coachMessage.value = 'Checking your answer.';
    coachState.value = 'checking';

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
            }),
        });
        const result = await response.json();

        if (!response.ok) {
            const message = result.message ?? Object.values(result.errors ?? {})?.[0]?.[0] ?? 'We could not check this item yet.';
            throw new Error(message);
        }

        retryStates[item.id] = result.retry_state ?? defaultRetryState();
        step.feedback.value = result.message ?? retryStates[item.id].feedback ?? '';

        if (retryStates[item.id].is_correct) {
            coachMessage.value = 'That is correct. Go to the next one.';
            coachState.value = step.isLast.value ? 'section_complete' : 'correct';
        } else if (retryStates[item.id].can_retry) {
            coachMessage.value = 'Try this same item again.';
            coachState.value = Number(retryStates[item.id].attempt_count ?? 1) <= 1
                ? 'incorrect'
                : 'retry';
            clearAudio(item, false);
            if (canUseManualFallback.value) {
                step.answers[item.id] = '';
            }
        } else {
            coachMessage.value = 'Good try. Go to the next one.';
            coachState.value = 'speaking';
        }

        return retryStates[item.id].is_resolved;
    } catch (error) {
        step.feedback.value = error.message || 'We could not check this item yet.';
        coachMessage.value = step.feedback.value;
        coachState.value = 'error';
        return false;
    } finally {
        checking[item.id] = false;
    }
};

const submit = () => {
    form.post(`/learner/modules/${props.module.key}/activity/${props.activityType}`, { forceFormData: true });
};

const handlePrimary = async () => {
    if (!isCurrentResolved.value) {
        await checkCurrent();
        return;
    }

    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
    coachMessage.value = 'Read the prompt, then record your voice. I will help you practice.';
    coachState.value = 'speaking';
};

const returnToDashboard = () => {
    if (returningToDashboard.value) return;
    returningToDashboard.value = true;
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
    coachMessage.value = 'See you next time!';
    coachState.value = 'happy';
    window.setTimeout(() => {
        window.location.href = '/learner/dashboard';
    }, 1200);
};
</script>

<template>
    <LearnerLayout :progress="82">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="coach_feedback" :state="coachState" :message="coachMessage" />
        </template>

        <section class="mx-auto grid max-w-2xl gap-4 xl:gap-5">
            <div class="flex items-center justify-between">
                <StatusBadge :status="activityLabel" variant="primary" />
                <StatusBadge :status="progressLabel" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard label="Practice" :prompt="step.currentItem.value.prompt" :highlight-targets="currentHighlightTargets" size="word" />
            <div class="rounded-[32px] border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/30 xl:p-7">
                <div class="grid gap-5 md:grid-cols-[minmax(220px,1fr)_1.3fr] md:items-start xl:gap-6">
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        :reset-key="`${step.currentItem.value.id}-${recorderResetKeys[step.currentItem.value.id] ?? 0}`"
                        compact
                        :max-duration-seconds="45"
                        :prompt-type="recorderPromptType"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="isCurrentUploading || isCurrentChecking"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Practice voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                        @error="handleRecorderError"
                    />
                    <div class="grid gap-4">
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
                        <label class="grid gap-2 text-base font-black text-slate-800 xl:text-lg">
                            You said
                            <textarea :value="generatedTranscripts[step.currentItem.value.id] ?? ''" class="learner-transcript-box resize-none rounded-[20px] border border-slate-200/80 bg-slate-50/50 p-4 text-base font-bold text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200/50 xl:p-5 xl:text-lg" readonly :placeholder="isCurrentUploading ? 'Checking your recording...' : 'Your words will appear here'" />
                        </label>
                        <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-slate-500">
                            Developer QA: Manual Transcript Override
                            <input v-model="step.answers[step.currentItem.value.id]" class="rounded-[16px] border border-slate-200/80 bg-white px-4 py-3 text-base font-bold focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200/50" placeholder="Optional QA fallback text">
                        </label>
                    </div>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-[20px] bg-orange-50 px-5 py-3 text-sm font-black text-orange-600 ring-1 ring-orange-200/60">{{ uploadErrors[step.currentItem.value.id] }}</p>
                <p v-if="step.feedback.value" class="mt-4 rounded-[20px] bg-blue-50 px-5 py-3 text-base font-black text-blue-700 ring-1 ring-blue-200/60">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full flex-col-reverse items-center justify-between gap-4 sm:flex-row">
                <button
                    type="button"
                    class="group inline-flex w-full items-center justify-center gap-2 rounded-[22px] border-2 border-slate-200/80 bg-white px-6 py-3.5 text-base font-bold text-slate-600 transition-all hover:border-slate-300 hover:bg-slate-50 sm:w-auto xl:px-8 xl:text-lg"
                    :disabled="returningToDashboard || form.processing || isCurrentUploading || isCurrentChecking"
                    @click="returnToDashboard"
                >
                    <ArrowLeft class="size-5 stroke-[2.5] transition-transform group-hover:-translate-x-1" />
                    <span>Back to Learner Dashboard</span>
                </button>
                <PrimaryButton
                    class="group w-full gap-3 rounded-[22px] px-8 py-3.5 text-base shadow-xl shadow-primary/25 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] active:scale-[0.98] sm:w-auto xl:text-lg"
                    :disabled="primaryDisabled"
                    :class="{ 'opacity-70': primaryDisabled }"
                    @click="handlePrimary"
                >
                    {{ primaryLabel }}
                    <ArrowRight class="size-5 stroke-[3] transition-transform group-hover:translate-x-1 sm:size-6" />
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
