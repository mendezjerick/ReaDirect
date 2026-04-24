<script setup>
import { computed, reactive, watch } from 'vue';
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

const props = defineProps({ module: Object, items: Array });
const step = useStepAssessment(props.items, { emptyMessage: 'Try this one before moving on.' });
const form = useForm({ responses: [] });
const audioFiles = reactive({});
const audioDurations = reactive({});
const progressLabel = computed(() => `Mastery ${step.currentIndex.value + 1} of ${props.items.length}`);

watch(
    () => props.items.map((item) => item.id).join('|'),
    () => {
        step.reset(props.items);
        Object.keys(audioFiles).forEach((key) => delete audioFiles[key]);
        Object.keys(audioDurations).forEach((key) => delete audioDurations[key]);
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

const submit = () => {
    if (!step.validateCurrent()) return;

    form.responses = step.payload((item, answer) => ({
        module_attempt_item_id: item.id,
        answer,
        transcript_source: 'manual',
        audio: audioFiles[item.id] ?? null,
        duration_seconds: audioDurations[item.id] ?? null,
    }));
    form.post(`/learner/modules/${props.module.key}/mastery-check`, { forceFormData: true });
};

const handlePrimary = () => {
    if (!step.validateCurrent()) return;

    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="90">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="coach_feedback" state="encouraging" message="This is your mini mastery check. Do your best one item at a time." />
        </template>

        <section class="mx-auto grid max-w-2xl gap-4">
            <div class="flex items-center justify-between">
                <StatusBadge status="Mini Mastery Check" variant="primary" />
                <StatusBadge :status="progressLabel" />
            </div>
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard label="Check" :prompt="step.currentItem.value.prompt" size="word" />
            <div class="rounded-[28px] border border-border bg-surface p-5 shadow-lg shadow-primary/10">
                <div class="grid gap-4 md:grid-cols-[240px_1fr] md:items-center">
                    <AudioRecorder
                        compact
                        :max-duration-seconds="45"
                        label="Check voice"
                        @recorded="(file) => rememberAudio(step.currentItem.value, file)"
                        @cleared="() => clearAudio(step.currentItem.value)"
                    />
                    <label class="grid gap-2 text-lg font-black text-text">
                        Your answer
                        <input v-model="step.answers[step.currentItem.value.id]" class="rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none" placeholder="Type answer">
                    </label>
                </div>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Finish check' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
