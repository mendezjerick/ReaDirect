<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';
import { appendAudioMetadata, normalizeAsrResponse } from '../../../utils/asrResponse';

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
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));
const currentHasUploadedAudio = computed(() => Boolean(uploadedAudioIds[step.currentItem.value?.id]));
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');

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
    form.post('/final-assessment/task-2b/submit', {
        forceFormData: true,
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? 'We could not check these words yet. Please review them and try again.';
            step.feedback.value = Array.isArray(firstError) ? firstError[0] : firstError;
            agentMessage.value = step.feedback.value;
            agentState.value = 'speaking';
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

    agentMessage.value = 'Thank you. Let us continue.';
    agentState.value = 'speaking';
    if (step.isLast.value) return submit();
    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="55">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" :state="agentState" :message="agentMessage" />
        </template>

        <section class="relative mx-auto grid w-full max-w-[960px] gap-4 sm:gap-5 xl:gap-6">
            <span class="pointer-events-none absolute -left-14 top-12 hidden text-4xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute -right-8 bottom-12 hidden text-3xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <div class="pointer-events-none absolute -left-20 top-0 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 bottom-0 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" aria-hidden="true" />

            <!-- Progress header -->
            <div class="anim-fade-down grid gap-3 px-1">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-primary/5 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10">
                        Word {{ step.currentIndex.value + 1 }} of {{ items.length }}
                    </span>
                    <span class="rounded-full bg-primary/8 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/15">
                        {{ isCurrentUploading ? '🔄 Checking' : '🎙️ Voice check' }}
                    </span>
                </div>
                <div class="h-3.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500 ease-out" :style="{ width: `${step.progressPercent.value}%` }" />
                </div>
            </div>

            <!-- Sentence prompt card -->
            <div class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-6 text-center shadow-2xl shadow-primary/10 sm:p-7">
                <p class="text-base font-black text-slate-400">Read the highlighted word</p>
                <p class="mt-3 text-2xl font-black leading-snug text-slate-800 md:text-3xl">
                    <template v-for="(part, index) in parts(step.currentItem.value)" :key="index">
                        <mark v-if="part.toLowerCase() === (step.currentItem.value.payload?.target_word ?? '').toLowerCase()" class="rounded-xl bg-primary/8 px-2 text-primary">{{ part }}</mark>
                        <span v-else>{{ part }}</span>
                    </template>
                </p>
            </div>

            <!-- Recording card -->
            <div class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-6 shadow-2xl shadow-primary/10 sm:p-7">
                <div class="grid gap-4 md:grid-cols-[220px_1fr] md:items-center">
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        :reset-key="step.currentItem.value.id"
                        compact
                        :max-duration-seconds="30"
                        prompt-type="word"
                        :require-review-before-submit="requireReviewBeforeSubmit"
                        :auto-transcribe-on-stop="autoTranscribeOnStop"
                        :submitting="isCurrentUploading"
                        :submitted="Boolean(uploadedAudioIds[step.currentItem.value.id]) && !uploadErrors[step.currentItem.value.id]"
                        label="Word voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @submit="(file) => uploadAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <div class="grid gap-3">
                        <label class="grid gap-2 text-lg font-black text-text">
                            You said
                            <textarea :value="generatedTranscripts[step.currentItem.value.id] ?? ''" class="learner-transcript-box resize-none rounded-2xl border-2 border-border bg-background font-black text-text focus:border-primary focus:outline-none" readonly :placeholder="isCurrentUploading ? 'Checking your recording...' : 'Your words will appear here'" />
                        </label>
                        <label v-if="canUseManualFallback" class="grid gap-2 text-sm font-black text-muted">
                            Developer QA: Manual Transcript Override
                            <input :value="step.answers[step.currentItem.value.id]" class="w-full rounded-2xl border-2 border-border px-4 py-3 text-base font-black text-text focus:border-primary focus:outline-none" placeholder="Optional QA fallback text" @input="setAnswer(step.currentItem.value, $event.target.value)">
                        </label>
                    </div>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-[20px] bg-amber-50 px-4 py-3 text-[13px] font-semibold text-amber-700 ring-1 ring-amber-200/60">{{ uploadErrors[step.currentItem.value.id] }}</p>
                <p v-if="firstFormError" class="mt-4 rounded-[20px] bg-amber-50 px-4 py-3 text-[13px] font-semibold text-amber-700 ring-1 ring-amber-200/60">{{ firstFormError }}</p>
                <p v-if="step.feedback.value" class="mt-4 rounded-[20px] bg-amber-50 px-4 py-3 text-[13px] font-semibold text-amber-700 ring-1 ring-amber-200/60">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || isCurrentUploading" :class="{ 'opacity-70': !step.isCurrentAnswered.value || isCurrentUploading }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check words' : 'Next' }}
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
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
