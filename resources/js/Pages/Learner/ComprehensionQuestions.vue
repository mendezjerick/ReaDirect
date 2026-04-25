<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';

const props = defineProps({ questions: Array });
const step = useStepAssessment(props.questions, { emptyMessage: 'Choose one answer before moving on.' });
const form = useForm({ responses: [] });

const choose = (choice) => {
    step.answers[step.currentItem.value.id] = choice;
    step.feedback.value = '';
};

const submit = () => {
    if (!step.validateCurrent()) return;

    form.responses = step.payload((question, answer) => ({ question_id: question.id, answer }));
    form.post('/learner/diagnostic/comprehension');
};

const handlePrimary = () => {
    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="86">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" state="speaking" message="Choose the best answer for this question." />
        </template>

        <section class="mx-auto grid max-w-xl gap-3">
            <StatusBadge :status="`Question ${step.currentIndex.value + 1} of ${questions.length}`" />
            <ModuleProgressBar :value="step.progressPercent.value" />
            <div class="rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10">
                <p class="text-xl font-black text-text md:text-2xl">{{ step.currentItem.value.question_text }}</p>
                <div class="mt-4 grid gap-2.5">
                    <button
                        v-for="(choice, key) in step.currentItem.value.choices"
                        :key="key"
                        type="button"
                        class="rounded-2xl border-2 px-4 py-3 text-left text-base font-black md:text-lg"
                        :class="step.answers[step.currentItem.value.id] === choice ? 'border-primary bg-primary-light text-primary' : 'border-border bg-surface text-text hover:border-primary'"
                        @click="choose(choice)"
                    >
                        {{ choice }}
                    </button>
                </div>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check answers' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
