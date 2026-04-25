<script setup>
import { reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';

const props = defineProps({ items: Array });
const form = useForm({ responses: [] });
const audioFiles = reactive({});
const audioDurations = reactive({});
const hasAnswerOrAudio = (item, answer) => String(answer ?? '').trim().length > 0 || Boolean(audioFiles[item?.id]);
const step = useStepAssessment(props.items, { emptyMessage: 'Almost there! Finish this item to continue.', isAnswered: hasAnswerOrAudio });
const agentMessage = ref('Read the highlighted word in the sentence.');
const agentState = ref('listening');
const neutralMessages = ['Thank you. Let us continue.', 'Good effort. Let us go to the next one.', 'I heard your answer. Let us keep going.'];

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
};

const clearAudio = (item) => {
    delete audioFiles[item.id];
    delete audioDurations[item.id];
};

const parts = (item) => {
    const target = item.payload?.target_word ?? '';
    if (!target) return [item.prompt];
    return item.prompt.split(new RegExp(`(${target})`, 'i'));
};

const submit = () => {
    if (!step.validateCurrent()) return;

    form.responses = step.payload((item, answer) => ({
        assessment_attempt_item_id: item.id,
        answer,
        transcript_source: String(answer ?? '').trim() ? 'manual' : 'stt_auto',
        audio: audioFiles[item.id] ?? null,
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post('/learner/diagnostic/task-2b', { forceFormData: true });
};

const handlePrimary = () => {
    if (!step.validateCurrent()) {
        agentMessage.value = 'Almost there! Finish this item to continue.';
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
    <LearnerLayout :progress="58">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" :state="agentState" :message="agentMessage" />
        </template>

        <section class="mx-auto grid max-w-xl gap-3">
            <StatusBadge :status="`Word ${step.currentIndex.value + 1} of ${items.length}`" />
            <ModuleProgressBar :value="step.progressPercent.value" />
            <div class="rounded-[28px] border border-border bg-surface p-5 text-center shadow-xl shadow-primary/10">
                <p class="text-base font-black text-muted">Read the sentence</p>
                <p class="mt-3 text-2xl font-black leading-snug text-text md:text-3xl">
                    <template v-for="(part, index) in parts(step.currentItem.value)" :key="index">
                        <mark v-if="part.toLowerCase() === (step.currentItem.value.payload?.target_word ?? '').toLowerCase()" class="rounded-xl bg-accent px-2">{{ part }}</mark>
                        <span v-else>{{ part }}</span>
                    </template>
                </p>
            </div>
            <div class="rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10">
                <div class="grid gap-3 md:grid-cols-[220px_1fr] md:items-center">
                    <AudioRecorder
                        compact
                        :max-duration-seconds="30"
                        label="Word voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <input v-model="step.answers[step.currentItem.value.id]" class="w-full rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none" placeholder="Type the target word read">
                </div>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check words' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
