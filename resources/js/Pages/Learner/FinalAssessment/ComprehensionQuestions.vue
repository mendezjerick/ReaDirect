<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    questions: Array,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});
const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const savedAnswers = Object.fromEntries((props.questions ?? []).filter((question) => question.saved_answer).map((question) => [question.id, question.saved_answer]));
const firstUnansweredIndex = (props.questions ?? []).findIndex((question) => !savedAnswers[question.id]);
const step = useStepAssessment(props.questions, {
    emptyMessage: 'Choose one answer before moving on.',
    initialIndex: firstUnansweredIndex === -1 ? Math.max((props.questions ?? []).length - 1, 0) : firstUnansweredIndex,
});
const form = useForm({ assessment_attempt_id: props.assessmentAttemptId, responses: [] });
Object.entries(savedAnswers).forEach(([id, answer]) => {
    step.answers[id] = answer;
});

const choose = (choice) => {
    step.answers[step.currentItem.value.id] = choice;
    step.feedback.value = '';
    fetch('/learner/assessment-progress/comprehension', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        },
        body: JSON.stringify({
            assessment_attempt_id: props.assessmentAttemptId,
            question_id: step.currentItem.value.id,
            answer: choice,
        }),
    }).catch(() => {});
};
const submit = () => {
    if (!step.validateCurrent()) return;
    form.responses = step.payload((question, answer) => ({ question_id: question.id, answer }));
    form.post('/final-assessment/comprehension/submit');
};
const handlePrimary = () => {
    if (step.isLast.value) return submit();
    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="86">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" state="speaking" message="Choose the best answer based on the story you read." />
        </template>
        <section class="mx-auto grid max-w-xl gap-3">
            <StatusBadge :status="`Question ${step.currentIndex.value + 1} of ${questions.length}`" />
            <ModuleProgressBar :value="step.progressPercent.value" />
            <div class="rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10">
                <p class="text-xl font-black text-text md:text-2xl">{{ step.currentItem.value.question_text }}</p>
                <div class="mt-4 grid gap-2.5">
                    <button v-for="(choice, key) in step.currentItem.value.choices" :key="key" type="button" class="rounded-2xl border-2 px-4 py-3 text-left text-base font-black md:text-lg" :class="step.answers[step.currentItem.value.id] === choice ? 'border-primary bg-primary-light text-primary' : 'border-border bg-surface text-text hover:border-primary'" @click="choose(choice)">
                        {{ choice }}
                    </button>
                </div>
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>
        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Finish final check' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
