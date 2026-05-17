<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MessageCircle, Mic2, Volume2 } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';

const props = defineProps({
    items: Array,
    initialIndex: Number,
    assessmentAttemptId: Number,
    assessmentMode: Object,
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
const uploadErrors = reactive({});
const uploading = reactive({});
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const isDeveloperQaMode = computed(() => props.assessmentMode?.isDeveloperQaMode === true);
const autoTranscribeOnStop = computed(() => props.assessmentMode?.canAutoTranscribeOnStop === true);
const requireReviewBeforeSubmit = computed(() => props.assessmentMode?.requireReviewBeforeSubmit !== false);
const manualAnswerFor = (item) => canUseManualFallback.value ? String(step.answers[item?.id] ?? '').trim() : '';
const answerFor = (item) => manualAnswerFor(item) || String(generatedTranscripts[item?.id] ?? '').trim();
const sourceFor = (item) => manualAnswerFor(item)
    ? 'manual'
    : (transcriptSources[item?.id] ?? (generatedTranscripts[item?.id] ? 'stt_auto' : 'stt_auto'));
const hasAnswerOrAudio = (item) => answerFor(item).length > 0;
const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.', initialIndex: props.initialIndex ?? 0, isAnswered: hasAnswerOrAudio });
const agentMessage = ref('Say the letter out loud. Record your answer when you are ready.');
const agentState = ref('listening');
const neutralMessages = ['Thank you. Let us continue.', 'Good effort. Let us go to the next one.', 'I heard your answer. Let us keep going.'];
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');

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

        if (!response.ok) {
            throw new Error(result.message ?? 'We had trouble checking your answer. Please try again.');
        }

        const asr = normalizeAsrResponse(result);
        if (asr.canSubmit) {
            uploadedAudioIds[item.id] = result.audio_file_id;
            const transcript = asr.displayTranscript;
            generatedTranscripts[item.id] = transcript;
            transcriptSources[item.id] = result.transcript_source ?? 'stt_auto';
            agentMessage.value = `You said: ${transcript}`;
            agentState.value = 'speaking';
            return;
        }

        uploadErrors[item.id] = asr.message;
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
    form.post('/learner/diagnostic/task-1', {
        forceFormData: true,
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? 'We could not check these answers yet. Please review the letters and try again.';
            step.feedback.value = Array.isArray(firstError) ? firstError[0] : firstError;
            agentMessage.value = step.feedback.value;
            agentState.value = 'speaking';
        },
    });
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

    agentMessage.value = neutralMessages[step.currentIndex.value % neutralMessages.length];
    agentState.value = 'speaking';

    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="35" diagnostic-step="task-1">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                :state="agentState"
                :message="agentMessage"
                presentation="assessment-task"
            />
        </template>

        <section class="mx-auto grid w-full max-w-[1120px] gap-5">
            <div class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3 px-1">
                    <p class="text-base font-black text-primary">
                        Letter {{ step.currentIndex.value + 1 }} of {{ items.length }}
                    </p>
                    <p class="inline-flex items-center gap-2 text-sm font-black text-primary">
                        <Volume2 class="size-5" />
                        {{ isCurrentUploading ? 'Checking' : 'Voice check' }}
                    </p>
                </div>
                <div class="h-4 overflow-hidden rounded-full bg-primary-light">
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-300"
                        :style="{ width: `${step.progressPercent.value}%` }"
                    />
                </div>
            </div>

            <section class="relative overflow-hidden rounded-[28px] border border-blue-100 bg-surface px-6 py-7 text-center shadow-xl shadow-primary/10 sm:px-10 sm:py-8">
                <span class="absolute left-6 top-8 size-14 rounded-full bg-primary-light/70" aria-hidden="true" />
                <span class="absolute right-8 top-8 text-3xl font-black text-blue-100" aria-hidden="true">*</span>
                <p class="relative text-lg font-black text-text sm:text-xl">
                    Letter {{ step.currentItem.value.sequence }}
                </p>
                <p class="relative mt-5 text-[clamp(5.5rem,10vw,8.5rem)] font-black leading-none text-slate-950">
                    {{ step.currentItem.value.prompt }}
                </p>
            </section>

            <section class="rounded-[28px] border border-blue-100 bg-surface p-3 shadow-xl shadow-primary/10 sm:p-4 lg:p-5">
                <div class="grid gap-4 lg:grid-cols-[300px_1fr] xl:grid-cols-[320px_1fr]">
                    <div class="rounded-[24px] border border-blue-100 bg-surface p-3 shadow-sm shadow-primary/10">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="grid size-12 place-items-center rounded-full bg-primary-light text-primary">
                                    <Mic2 class="size-6" />
                                </span>
                                <div>
                                    <p class="text-lg font-black text-text">Letter voice</p>
                                    <p class="text-sm font-bold leading-snug text-muted">
                                        Tap Start Recording or press Space.
                                    </p>
                                </div>
                            </div>
                            <span class="rounded-full bg-success/10 px-3 py-1.5 text-xs font-black text-success">
                                {{ isCurrentUploading ? 'Checking' : 'Ready' }}
                            </span>
                        </div>
                        <AudioRecorder
                            :key="step.currentItem.value.id"
                            :reset-key="step.currentItem.value.id"
                            compact
                            :max-duration-seconds="30"
                            :require-review-before-submit="requireReviewBeforeSubmit"
                            :auto-transcribe-on-stop="autoTranscribeOnStop"
                            :submitting="isCurrentUploading"
                            :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                            label="Letter voice"
                            prompt-type="letter"
                            @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                            @submit="(file) => uploadAudio(step.currentItem.value, file)"
                            @cleared="() => clearAudio(step.currentItem.value)"
                        />
                    </div>

                    <div class="grid gap-3 rounded-[24px] border border-blue-100 bg-surface p-4 shadow-sm shadow-primary/10 sm:p-5">
                        <label class="grid gap-3 text-lg font-black text-text">
                            <span class="inline-flex items-center gap-3">
                                <span class="grid size-11 place-items-center rounded-full bg-primary-light text-primary">
                                    <MessageCircle class="size-6" />
                                </span>
                                You said
                            </span>
                            <textarea
                                :value="generatedTranscripts[step.currentItem.value.id] ?? ''"
                                class="learner-transcript-box min-h-44 resize-none rounded-[22px] border-2 border-blue-100 bg-background p-5 text-xl font-black text-text focus:border-primary focus:outline-none sm:min-h-52"
                                readonly
                                :placeholder="isCurrentUploading ? 'Checking your recording...' : 'Your words will appear here'"
                            />
                        </label>
                        <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-muted">
                            Developer QA: Manual Transcript Override
                            <input
                                :value="step.answers[step.currentItem.value.id]"
                                class="rounded-2xl border-2 border-border px-4 py-3 text-base font-black text-text focus:border-primary focus:outline-none"
                                placeholder="Optional QA fallback text"
                                @input="setAnswer(step.currentItem.value, $event.target.value)"
                            >
                        </label>
                    </div>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">
                    {{ uploadErrors[step.currentItem.value.id] }}
                </p>
                <p v-if="firstFormError" class="mt-4 rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">{{ firstFormError }}</p>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </section>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || isCurrentUploading" :class="{ 'opacity-70': !step.isCurrentAnswered.value || isCurrentUploading }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check Answer' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
