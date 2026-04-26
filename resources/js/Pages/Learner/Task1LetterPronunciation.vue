<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';

const props = defineProps({
    items: Array,
    assessmentAttemptId: Number,
});
const form = useForm({ responses: [] });
const audioFiles = reactive({});
const audioDurations = reactive({});
const uploadedAudioIds = reactive({});
const transcriptSources = reactive({});
const generatedTranscripts = reactive({});
const uploadErrors = reactive({});
const uploading = reactive({});
const hasAnswerOrAudio = (item, answer) => String(answer ?? '').trim().length > 0 || Boolean(audioFiles[item?.id]);
const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.', isAnswered: hasAnswerOrAudio });
const agentMessage = ref('Say this letter clearly. I will transcribe it, and you can correct the transcript before moving on.');
const agentState = ref('listening');
const neutralMessages = ['Thank you. Let us continue.', 'Good effort. Let us go to the next one.', 'I heard your answer. Let us keep going.'];
const isCurrentUploading = computed(() => Boolean(uploading[step.currentItem.value?.id]));

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
    uploadErrors[item.id] = '';
    uploadAudio(item, file);
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
    transcriptSources[item.id] = String(value ?? '').trim() ? 'manual' : (generatedTranscripts[item.id] ? 'stt_auto' : 'manual');
};

const uploadAudio = async (item, file) => {
    uploading[item.id] = true;
    agentMessage.value = 'Uploading voice and generating transcript.';
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
            throw new Error(result.message ?? 'Unable to transcribe the recording right now.');
        }

        const transcript = String(result.transcript ?? '').trim();
        uploadedAudioIds[item.id] = result.audio_file_id;
        if (transcript) {
            generatedTranscripts[item.id] = transcript;
            step.answers[item.id] = transcript;
            transcriptSources[item.id] = 'stt_auto';
            agentMessage.value = `Transcript ready: ${transcript}`;
            agentState.value = 'speaking';
            return;
        }

        transcriptSources[item.id] = 'manual';
        uploadErrors[item.id] = 'No transcript was produced. Enter the transcript manually.';
        agentMessage.value = 'No transcript was produced. Enter the transcript manually.';
        agentState.value = 'speaking';
    } catch (error) {
        transcriptSources[item.id] = 'manual';
        uploadErrors[item.id] = error.message || 'Unable to transcribe the recording right now.';
        agentMessage.value = uploadErrors[item.id];
        agentState.value = 'speaking';
    } finally {
        uploading[item.id] = false;
    }
};

const submit = () => {
    if (!step.validateCurrent()) return;

    form.responses = step.payload((item, answer) => ({
        assessment_attempt_item_id: item.id,
        answer,
        transcript_source: transcriptSources[item.id] ?? (String(answer ?? '').trim() ? 'manual' : 'stt_auto'),
        audio_file_id: uploadedAudioIds[item.id] ?? null,
        audio: uploadedAudioIds[item.id] ? null : (audioFiles[item.id] ?? null),
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post('/learner/diagnostic/task-1', { forceFormData: true });
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
    <LearnerLayout :progress="35">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" :state="agentState" :message="agentMessage" />
        </template>

        <section class="mx-auto grid max-w-xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge :status="`Letter ${step.currentIndex.value + 1} of ${items.length}`" />
                <StatusBadge :status="isCurrentUploading ? 'Transcribing' : 'Voice transcript'" variant="primary" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard :label="`Letter ${step.currentItem.value.sequence}`" :prompt="step.currentItem.value.prompt" size="letter" />
            <div class="rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10">
                <div class="grid gap-3 md:grid-cols-[220px_1fr] md:items-center">
                    <AudioRecorder
                        :key="step.currentItem.value.id"
                        compact
                        :max-duration-seconds="30"
                        label="Letter voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <label class="grid gap-2 text-lg font-black text-text">
                        Transcript
                        <input
                            :value="step.answers[step.currentItem.value.id]"
                            class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none"
                            :placeholder="isCurrentUploading ? 'Generating transcript...' : 'Transcript appears here after recording'"
                            @input="setAnswer(step.currentItem.value, $event.target.value)"
                        >
                    </label>
                </div>
                <p v-if="uploadErrors[step.currentItem.value.id]" class="mt-4 rounded-2xl bg-warning/15 px-4 py-3 text-sm font-black text-warning">
                    {{ uploadErrors[step.currentItem.value.id] }}
                </p>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || isCurrentUploading" :class="{ 'opacity-70': !step.isCurrentAnswered.value || isCurrentUploading }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check Answer' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
