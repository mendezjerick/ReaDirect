<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MessageCircle, Mic2, Volume2 } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AsrTranscriptVisualizer from '../../Components/Learner/AsrTranscriptVisualizer.vue';
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
    delete asrResults[item.id];
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
            agentState.value = 'retry';
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
                compact
                agent-type="assessment"
                :state="agentState"
                :message="agentMessage"
                presentation="assessment-task"
            />
        </template>

        <section class="mx-auto grid w-full max-w-[1120px] gap-5">
            <!-- Progress header -->
            <div class="anim-fade-down grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3 px-1">
                    <p class="inline-flex items-center gap-2.5 text-[15px] font-black text-slate-700">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-blue-600 text-[12px] font-black text-white shadow-sm shadow-primary/20">
                            {{ step.currentIndex.value + 1 }}
                        </span>
                        Letter {{ step.currentIndex.value + 1 }} of {{ items.length }}
                    </p>
                    <p class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10">
                        <Volume2 class="size-4" />
                        {{ isCurrentUploading ? 'Checking' : 'Voice check' }}
                    </p>
                </div>
                <div class="h-3.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500 ease-out"
                        :style="{ width: `${step.progressPercent.value}%` }"
                    />
                </div>
            </div>

            <!-- Letter display card -->
            <section
                :key="step.currentItem.value.id + '-card'"
                class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white px-4 py-6 text-center shadow-2xl shadow-primary/10 sm:px-8 sm:py-8"
            >
                <!-- Decorative blobs -->
                <span class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
                <span class="pointer-events-none absolute -bottom-10 -right-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
                <!-- Sparkle decorations -->
                <span class="pointer-events-none absolute left-6 top-6 text-4xl font-black text-primary/5" aria-hidden="true">✦</span>
                <span class="pointer-events-none absolute right-8 top-8 text-4xl font-black text-primary/5" aria-hidden="true">✦</span>

                <p class="relative text-[14px] font-black uppercase tracking-widest text-slate-400">
                    Letter {{ step.currentItem.value.sequence }}
                </p>
                <p class="anim-pop relative mt-5 bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-[30vw] sm:text-[22vw] font-black leading-none text-transparent">
                    {{ step.currentItem.value.prompt }}
                </p>
            </section>

            <!-- Recording & transcript panel -->
            <section class="anim-slide-up rounded-[32px] border border-slate-200/80 bg-white p-3 shadow-xl shadow-slate-200/30 sm:p-4 lg:p-5">
                <div class="grid gap-4 lg:grid-cols-[240px_1fr] lg:grid-cols-[300px_1fr] xl:grid-cols-[320px_1fr]">
                    <!-- Mic / recorder panel -->
                    <div class="rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-4 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20">
                                    <Mic2 class="size-6" />
                                </span>
                                <div>
                                    <p class="text-[16px] font-black text-slate-800">Letter voice</p>
                                    <p class="text-[12px] font-semibold leading-snug text-slate-400">
                                        Tap Start Recording or press Space.
                                    </p>
                                </div>
                            </div>
                            <span
                                :class="isCurrentUploading
                                    ? 'bg-amber-50 text-amber-600 ring-1 ring-amber-200/60'
                                    : 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200/60'"
                                class="rounded-full px-3 py-1.5 text-[12px] font-black"
                            >
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

                    <!-- Transcript panel -->
                    <div class="grid gap-4 rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-5 shadow-sm">
                        <label class="grid gap-3 text-[16px] font-black text-slate-800">
                            <span class="inline-flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-md shadow-violet-500/20">
                                    <MessageCircle class="size-5" />
                                </span>
                                You said
                            </span>
                            <AsrTranscriptVisualizer
                                :transcript="generatedTranscripts[step.currentItem.value.id] ?? ''"
                                :expected-text="step.currentItem.value.payload?.expected_answer ?? step.currentItem.value.prompt"
                                :asr-result="asrResults[step.currentItem.value.id]"
                                :is-processing="isCurrentUploading"
                                :error="uploadErrors[step.currentItem.value.id] ?? ''"
                                box-class="min-h-44 resize-none rounded-[20px] border-2 border-slate-200/80 bg-white p-5 text-xl font-black text-slate-800 transition-all placeholder:text-slate-300 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                            />
                        </label>
                        <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-slate-400">
                            Developer QA: Manual Transcript Override
                            <input
                                :value="step.answers[step.currentItem.value.id]"
                                class="rounded-[20px] border-2 border-slate-200/80 bg-white px-4 py-3 text-base font-black text-slate-800 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                                placeholder="Optional QA fallback text"
                                @input="setAnswer(step.currentItem.value, $event.target.value)"
                            >
                        </label>
                    </div>
                </div>

                <!-- Error / feedback messages -->
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">
                    {{ uploadErrors[step.currentItem.value.id] }}
                </p>
                <p v-if="firstFormError" class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">{{ firstFormError }}</p>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-lg font-black text-amber-700 ring-1 ring-amber-200/60">{{ step.feedback.value }}</p>
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

<style scoped>
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}

.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
