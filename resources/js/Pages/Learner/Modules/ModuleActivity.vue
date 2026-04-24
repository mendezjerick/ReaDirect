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
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    module: Object,
    activityType: String,
    activityLabel: String,
    items: Array,
    nextActivityType: String,
});

const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.' });
const form = useForm({ responses: [] });
const retries = reactive({});
const audioFiles = reactive({});
const audioDurations = reactive({});
const coachMessage = ref('Read the prompt, then type what you said. I will help you practice.');
const coachState = ref('speaking');

const normalize = (value) => String(value ?? '').toLowerCase().trim().replace(/[^\w\s]/g, '').replace(/\s+/g, ' ');
const isAccepted = (item, answer) => item.accepted_answers.map(normalize).includes(normalize(answer));

const progressLabel = computed(() => `Activity ${step.currentIndex.value + 1} of ${props.items.length}`);

watch(
    () => props.items.map((item) => item.id).join('|'),
    () => {
        step.reset(props.items);
        Object.keys(retries).forEach((key) => delete retries[key]);
        Object.keys(audioFiles).forEach((key) => delete audioFiles[key]);
        Object.keys(audioDurations).forEach((key) => delete audioDurations[key]);
        coachMessage.value = 'Read the prompt, then type what you said. I will help you practice.';
        coachState.value = 'speaking';
        form.clearErrors();
        form.responses = [];
    }
);

const rememberAudio = (item, file) => {
    audioFiles[item.id] = file;
    audioDurations[item.id] = file.durationSeconds ?? null;
};

const clearAudio = (item) => {
    delete audioFiles[item.id];
    delete audioDurations[item.id];
};

const tryCurrent = () => {
    if (!step.validateCurrent()) {
        coachMessage.value = 'Let us answer this first.';
        coachState.value = 'encouraging';
        return false;
    }

    const item = step.currentItem.value;
    const answer = step.answers[item.id];

    if (!isAccepted(item, answer)) {
        retries[item.id] = (retries[item.id] ?? 0) + 1;
        step.feedback.value = 'Great effort! Try this one again.';
        coachMessage.value = 'Great effort! Listen carefully, then try again.';
        coachState.value = 'confused';
        return false;
    }

    step.feedback.value = 'Nice reading!';
    coachMessage.value = 'Nice reading! Keep going.';
    coachState.value = 'happy';
    return true;
};

const submit = () => {
    if (!tryCurrent()) return;

    form.responses = step.payload((item, answer) => ({
        module_attempt_item_id: item.id,
        answer,
        retry_count: retries[item.id] ?? 0,
        transcript_source: 'manual',
        audio: audioFiles[item.id] ?? null,
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post(`/learner/modules/${props.module.key}/activity/${props.activityType}`, { forceFormData: true });
};

const handlePrimary = () => {
    if (!tryCurrent()) return;

    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="82">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="coach_feedback" :state="coachState" :message="coachMessage" />
        </template>

        <section class="mx-auto grid max-w-2xl gap-4">
            <div class="flex items-center justify-between">
                <StatusBadge :status="activityLabel" variant="primary" />
                <StatusBadge :status="progressLabel" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard label="Practice" :prompt="step.currentItem.value.prompt" size="word" />
            <div class="rounded-[28px] border border-border bg-surface p-5 shadow-lg shadow-primary/10">
                <div class="grid gap-4 md:grid-cols-[240px_1fr] md:items-center">
                    <AudioRecorder
                        compact
                        :max-duration-seconds="45"
                        label="Practice voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <label class="grid gap-2 text-lg font-black text-text">
                        Your answer
                        <input v-model="step.answers[step.currentItem.value.id]" class="rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none" placeholder="Type answer">
                    </label>
                </div>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-primaryLight px-4 py-3 text-lg font-black text-primaryDark">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? (nextActivityType ? 'Finish activity' : 'Start mastery check') : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
