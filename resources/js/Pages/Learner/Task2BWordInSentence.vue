<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { BookOpen, MessageCircle, Mic2, Volume2 } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../Components/ModuleProgressBar.vue';
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
const hasManualOverride = (item) => canUseManualFallback.value && manualAnswerFor(item).length > 0;
const hasUsableTranscript = (item, answer) => {
    const expectedPrompt = String(item?.payload?.target_word ?? item?.payload?.expected_answer ?? item?.prompt ?? '').trim();
    const manualAnswer = String(answer ?? '').trim();
    const normalizedAnswer = manualAnswer || answerFor(item);

    if (!normalizedAnswer) return false;
    if (/^\d+$/.test(normalizedAnswer)) return false;
    if (!expectedPrompt) return normalizedAnswer.length > 0;

    return normalizedAnswer.length >= Math.max(2, Math.floor(expectedPrompt.length * 0.6));
};
const hasAnswerOrAudio = (item, answer) => (Boolean(uploadedAudioIds[item?.id]) || hasManualOverride(item)) && hasUsableTranscript(item, answer);
const step = useStepAssessment(props.items, { emptyMessage: 'Almost there! Finish this item to continue.', initialIndex: props.initialIndex ?? 0, isAnswered: hasAnswerOrAudio });
const agentMessage = ref('Read the word in the sentence. Speak clearly when you record.');
const agentState = ref('listening');
const neutralMessages = ['Thank you. Let us continue.', 'Good effort. Let us go to the next one.', 'I heard your answer. Let us keep going.'];
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const currentHasUploadedAudio = computed(() => Boolean(uploadedAudioIds[step.currentItem.value?.id]));
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');
const currentTranscript = computed(() => String(generatedTranscripts[step.currentItem.value?.id] ?? '').trim());

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    step.feedback.value = '';
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

const parts = (item) => {
    const target = item.payload?.target_word ?? '';
    if (!target) return [item.prompt];
    return item.prompt.split(new RegExp(`(${target})`, 'i'));
};

const submit = () => {
    if (!step.validateComplete()) {
        agentMessage.value = 'Almost there. Finish each sentence before checking your words.';
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
    form.post('/learner/diagnostic/task-2b', {
        forceFormData: true,
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? 'We could not check these sentences yet. Please review them and try again.';
            step.feedback.value = Array.isArray(firstError) ? firstError[0] : firstError;
            agentMessage.value = step.feedback.value;
            agentState.value = 'speaking';
        },
    });
};

const handlePrimary = () => {
    if (!currentHasUploadedAudio.value && !hasManualOverride(step.currentItem.value)) {
        agentMessage.value = canUseManualFallback.value
            ? 'Record the highlighted word, or enter a QA manual transcript override.'
            : 'Please record the highlighted word first so we can check what you said.';
        agentState.value = 'speaking';
        step.feedback.value = canUseManualFallback.value
            ? 'Record this item or enter a QA transcript override before continuing.'
            : 'Record the highlighted word before going to the next one.';
        return;
    }

    if (!hasUsableTranscript(step.currentItem.value, answerFor(step.currentItem.value))) {
        agentMessage.value = canUseManualFallback.value
            ? 'Please wait for the transcript, or correct it so it matches what you said.'
            : 'Please wait for the voice check, or try recording again.';
        agentState.value = 'speaking';
        step.feedback.value = canUseManualFallback.value
            ? 'We need a usable transcript for this word before continuing.'
            : 'We need to hear the word clearly before continuing.';
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
    <LearnerLayout :progress="58" diagnostic-step="task-2b">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                presentation="assessment-task"
                :state="agentState"
                :message="agentMessage"
            />
        </template>

        <section class="mx-auto grid max-w-6xl gap-5 rounded-[28px] border border-blue-100 bg-surface p-7 shadow-xl shadow-primary/10">
            <div class="flex items-center justify-between">
                <StatusBadge :status="`Sentence ${step.currentIndex.value + 1} of ${items.length}`" />
                <span class="inline-flex items-center gap-2 text-sm font-black text-primary">
                    Voice check
                    <Volume2 class="size-4" />
                </span>
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <div class="relative overflow-hidden rounded-[28px] border border-blue-100 bg-surface p-8 text-center shadow-lg shadow-primary/10">
                <span class="absolute left-6 top-6 grid size-16 place-items-center rounded-full bg-primary-light text-primary">
                    <BookOpen class="size-9" />
                </span>
                <p class="text-lg font-black text-muted">Read the highlighted word</p>
                <p class="mt-4 text-5xl font-black leading-snug text-slate-950 md:text-6xl">
                    <template v-for="(part, index) in parts(step.currentItem.value)" :key="index">
                        <mark v-if="part.toLowerCase() === (step.currentItem.value.payload?.target_word ?? '').toLowerCase()" class="rounded-2xl bg-accent px-4 py-1 text-slate-950">{{ part }}</mark>
                        <span v-else>{{ part }}</span>
                    </template>
                </p>
            </div>
            <div class="grid gap-5 lg:grid-cols-[340px_1fr]">
                <div class="rounded-[24px] border border-blue-100 bg-surface p-4 shadow-lg shadow-primary/10">
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        :reset-key="step.currentItem.value.id"
                        :max-duration-seconds="30"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="isCurrentUploading"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Sentence voice"
                        prompt-type="word"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                </div>
                <div class="grid gap-4 rounded-[24px] border border-blue-100 bg-surface p-5 shadow-lg shadow-primary/10">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-full bg-primary-light text-primary">
                            <MessageCircle class="size-5" />
                        </span>
                        <p class="text-xl font-black text-text">You said</p>
                    </div>
                    <div class="grid min-h-72 rounded-2xl border-2 border-blue-100 bg-blue-50/30 p-8 text-2xl font-black leading-snug text-slate-950">
                        <p v-if="isCurrentUploading" class="place-self-center text-center text-muted">Checking your recording...</p>
                        <p v-else-if="currentTranscript">{{ currentTranscript }}</p>
                        <div v-else class="grid place-items-center gap-3 text-center text-muted">
                            <span class="grid size-16 place-items-center rounded-full bg-primary-light text-muted">
                                <Mic2 class="size-9" />
                            </span>
                            <span>Your words will appear here</span>
                        </div>
                    </div>
                    <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-muted">
                        Developer QA: Manual Transcript Override
                        <input
                            :value="step.answers[step.currentItem.value.id]"
                            class="w-full rounded-2xl border-2 border-border px-4 py-3 text-base font-black text-text focus:border-primary focus:outline-none"
                            placeholder="Optional QA fallback text"
                            @input="setAnswer(step.currentItem.value, $event.target.value)"
                        >
                    </label>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning lg:col-span-2">
                    {{ uploadErrors[step.currentItem.value.id] }}
                </p>
                <p v-if="firstFormError" class="rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning lg:col-span-2">{{ firstFormError }}</p>
                <p v-if="step.feedback.value" class="rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text lg:col-span-2">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || isCurrentUploading" :class="{ 'opacity-70': !step.isCurrentAnswered.value || isCurrentUploading }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check sentence' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
