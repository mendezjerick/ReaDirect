<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { BookOpen, MessageCircle, Mic2, Volume2 } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AsrTranscriptVisualizer from '../../Components/Learner/AsrTranscriptVisualizer.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../utils/asrResponse';
import { getWordImage } from '../../utils/readingIllustrations';

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
const asrResults = reactive({});
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
const agentMessage = ref('Read the word in the sentence. Speak clearly when you record.');
const agentState = ref('listening');
const neutralMessages = ['Thank you. Let us continue.', 'Good effort. Let us go to the next one.', 'I heard your answer. Let us keep going.'];
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const currentHasUploadedAudio = computed(() => Boolean(uploadedAudioIds[step.currentItem.value?.id]));
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');
const currentTranscript = computed(() => String(generatedTranscripts[step.currentItem.value?.id] ?? '').trim());
const currentWordImage = computed(() => getWordImage(step.currentItem.value?.payload?.target_word));

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    delete uploadedAudioIds[item.id];
    delete transcriptSources[item.id];
    delete generatedTranscripts[item.id];
    delete asrResults[item.id];
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
    delete asrResults[item.id];
    delete uploadErrors[item.id];
    delete uploading[item.id];
};

const setAnswer = (item, value) => {
    step.answers[item.id] = value;
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    agentMessage.value = 'Checking your recording.';
    agentState.value = 'thinking';

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
            step.feedback.value = '';
            agentMessage.value = `You said: ${transcript}`;
            agentState.value = 'speaking';
            return;
        }

        uploadErrors[item.id] = asr.message;
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'retry';
    } catch (error) {
        uploadErrors[item.id] = error.message || 'We had trouble checking your answer. Please try again.';
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'retry';
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
            agentState.value = 'retry';
        },
    });
};

const handlePrimary = async () => {
    const currentItem = step.currentItem.value;

    if (currentItem?.id && !currentHasUploadedAudio.value && audioFiles[currentItem.id] && !isCurrentUploading.value) {
        await uploadAudio(currentItem, audioFiles[currentItem.id]);
        return;
    }

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
                compact
                agent-type="assessment"
                presentation="assessment-task"
                :state="agentState"
                :message="agentMessage"
            />
        </template>

        <section class="anim-main mx-auto grid max-w-6xl gap-5 rounded-[32px] border border-slate-200/80 bg-white p-4 sm:p-6 lg:p-7 shadow-xl shadow-slate-200/30">
            <!-- Progress header -->
            <div class="anim-fade-down flex items-center justify-between">
                <StatusBadge :status="`Sentence ${step.currentIndex.value + 1} of ${items.length}`" />
                <span class="rounded-full bg-primary/5 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10">
                    Voice check
                    <Volume2 class="mb-0.5 ml-1 inline size-3.5" />
                </span>
            </div>

            <!-- Gradient progress bar -->
            <div class="anim-fade-down h-3.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500 ease-out"
                    :style="{ width: `${step.progressPercent.value}%` }"
                />
            </div>

            <!-- Sentence card -->
            <div
                :key="step.currentItem.value.id"
                class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-5 sm:p-7 lg:p-8 text-center shadow-2xl shadow-primary/10"
            >
                <!-- Decorative blur blobs -->
                <span class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
                <span class="pointer-events-none absolute -bottom-10 -right-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" aria-hidden="true" />

                <!-- Sparkle decorations -->
                <span class="pointer-events-none absolute left-6 top-20 text-4xl font-black text-primary/5" aria-hidden="true">✦</span>
                <span class="pointer-events-none absolute bottom-6 right-8 text-4xl font-black text-primary/5" aria-hidden="true">✦</span>

                <div class="relative z-10">
                    <!-- Word illustration -->
                    <div v-if="currentWordImage" class="anim-pop mx-auto mb-4 flex items-center justify-center">
                        <img :src="currentWordImage" :alt="step.currentItem.value.payload?.target_word" class="h-[120px] w-[120px] rounded-[24px] object-contain drop-shadow-lg lg:h-[140px] lg:w-[140px]">
                    </div>
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20">
                        <BookOpen class="size-7" />
                    </span>
                    <p class="mt-4 text-[14px] font-black uppercase tracking-widest text-slate-400">Read the highlighted word</p>
                    <p class="anim-pop mt-5 text-5xl font-black leading-snug lg:text-6xl">
                        <template v-for="(part, index) in parts(step.currentItem.value)" :key="index">
                            <mark
                                v-if="part.toLowerCase() === (step.currentItem.value.payload?.target_word ?? '').toLowerCase()"
                                class="rounded-2xl bg-gradient-to-r from-amber-100 to-yellow-100 px-4 py-1 text-slate-800 ring-1 ring-amber-200/50"
                            >{{ part }}</mark>
                            <span v-else class="text-slate-800">{{ part }}</span>
                        </template>
                    </p>
                </div>
            </div>

            <!-- Recording + Transcript panels -->
            <div class="anim-slide-up grid gap-5 lg:grid-cols-[260px_1fr] lg:grid-cols-[340px_1fr]">
                <!-- Recording panel -->
                <div class="rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-4 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20">
                                <Mic2 class="size-6" />
                            </span>
                            <div>
                                <p class="text-[16px] font-black text-slate-800">Word voice</p>
                                <p class="text-[12px] font-semibold leading-snug text-slate-400">Record the highlighted word</p>
                            </div>
                        </div>
                        <span
                            v-if="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                            class="rounded-full bg-emerald-50 px-3 py-1 text-[12px] font-black text-emerald-600 ring-1 ring-emerald-200/60"
                        >Ready</span>
                        <span
                            v-else-if="isCurrentUploading"
                            class="rounded-full bg-amber-50 px-3 py-1 text-[12px] font-black text-amber-600 ring-1 ring-amber-200/60"
                        >Checking…</span>
                    </div>
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        :reset-key="step.currentItem.value.id"
                        :max-duration-seconds="30"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="isCurrentUploading"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Word voice"
                        prompt-type="word"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                </div>

                <!-- Transcript panel -->
                <div class="grid gap-4 rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-5 shadow-sm">
                    <label class="grid gap-3 text-[16px] font-black text-slate-800">
                        <span class="inline-flex items-center gap-3">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-md shadow-violet-500/20">
                                <MessageCircle class="size-5" />
                            </span>
                            You said
                        </span>
                    </label>
                    <AsrTranscriptVisualizer
                        :transcript="currentTranscript"
                        :expected-text="step.currentItem.value.payload?.target_word ?? step.currentItem.value.payload?.expected_answer ?? step.currentItem.value.prompt"
                        :asr-result="asrResults[step.currentItem.value.id]"
                        :is-processing="isCurrentUploading"
                        :error="uploadErrors[step.currentItem.value.id] ?? ''"
                        normal-mode="div"
                        box-class="grid min-h-40 rounded-[20px] border-2 border-slate-200/80 bg-white p-8 text-2xl font-black leading-snug text-slate-800 lg:min-h-72"
                    >
                        <template #normal="{ transcript, placeholder }">
                            <div class="grid min-h-40 rounded-[20px] border-2 border-slate-200/80 bg-white p-8 text-2xl font-black leading-snug text-slate-800 lg:min-h-72">
                        <p v-if="isCurrentUploading" class="place-self-center text-center text-[15px] font-semibold text-slate-400">Checking your recording…</p>
                        <p v-else-if="transcript">{{ transcript }}</p>
                        <div v-else class="grid place-items-center gap-3 text-center">
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white/60 shadow-lg shadow-primary/10">
                                <Mic2 class="size-7" />
                            </span>
                            <span class="text-[15px] font-semibold text-slate-400">{{ placeholder }}</span>
                        </div>
                            </div>
                        </template>
                    </AsrTranscriptVisualizer>
                    <label v-if="canUseManualFallback" class="grid gap-2 text-[14px] font-black text-slate-400">
                        Developer QA: Manual Transcript Override
                        <input
                            :value="step.answers[step.currentItem.value.id]"
                            class="w-full rounded-[20px] border-2 border-slate-200/80 bg-white px-4 py-3 text-base font-black text-slate-800 transition-all placeholder:text-slate-300 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                            placeholder="Optional QA fallback text"
                            @input="setAnswer(step.currentItem.value, $event.target.value)"
                        >
                    </label>
                </div>

                <!-- Upload errors -->
                <p v-if="uploadErrors[step.currentItem.value.id]" class="rounded-[20px] bg-rose-50 px-5 py-3 text-[14px] font-black text-rose-600 ring-1 ring-rose-200/60 lg:col-span-2">
                    {{ uploadErrors[step.currentItem.value.id] }}
                </p>
                <!-- Form errors -->
                <p v-if="firstFormError" class="rounded-[20px] bg-rose-50 px-5 py-3 text-[14px] font-black text-rose-600 ring-1 ring-rose-200/60 lg:col-span-2">{{ firstFormError }}</p>
                <!-- Feedback -->
                <p v-if="step.feedback.value" class="rounded-[20px] bg-amber-50 px-5 py-3.5 text-[15px] font-black text-amber-700 ring-1 ring-amber-200/60 lg:col-span-2">{{ step.feedback.value }}</p>
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

<style scoped>
/* Main section fade */
.anim-main {
    animation: mainFade 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes mainFade {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Header fade down */
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Card spring entrance */
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}

/* Content pop (sentence text) */
.anim-pop {
    animation: sentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes sentPop {
    from { opacity: 0; transform: scale(0.7); }
    to   { opacity: 1; transform: scale(1); }
}

/* Panel slide up */
.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
