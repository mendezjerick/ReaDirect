<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HelpCircle, Star } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';

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
    <LearnerLayout :progress="86" diagnostic-step="sentence-reading">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                state="speaking"
                presentation="comprehension"
                message="Choose the best answer based on the story you read."
            />
        </template>

        <section class="relative mx-auto grid w-full max-w-[960px] gap-4 sm:gap-5 xl:gap-6">
            <span class="pointer-events-none absolute -left-14 top-12 hidden text-4xl font-black text-accent xl:block" aria-hidden="true">*</span>
            <span class="pointer-events-none absolute -right-8 bottom-12 hidden text-3xl font-black text-blue-200 xl:block" aria-hidden="true">*</span>

            <div class="grid gap-3 px-1">
                <p class="text-base font-black text-primary xl:text-lg">
                    Question {{ step.currentIndex.value + 1 }} of {{ questions.length }}
                </p>
                <div class="h-4 overflow-hidden rounded-full bg-primary-light xl:h-5">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${step.progressPercent.value}%` }" />
                </div>
            </div>

            <div class="relative overflow-hidden rounded-[26px] border border-blue-100 bg-surface p-5 shadow-xl shadow-primary/10 sm:p-6 xl:rounded-[32px] xl:p-8">
                <div class="mb-5 flex items-start gap-4 sm:items-center xl:mb-7 xl:gap-5">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-14">
                        <HelpCircle class="size-7 stroke-[3] xl:size-9" />
                    </span>
                    <p class="text-2xl font-black leading-tight text-text sm:text-3xl xl:text-[34px]">{{ step.currentItem.value.question_text }}</p>
                </div>

                <div class="grid gap-3 xl:gap-4">
                    <button
                        v-for="(choice, key) in step.currentItem.value.choices"
                        :key="key"
                        type="button"
                        class="group grid min-h-16 grid-cols-[38px_1fr_auto] items-center gap-3 rounded-[16px] border-2 px-4 py-3 text-left text-lg font-black transition sm:min-h-20 sm:grid-cols-[44px_1fr_auto] sm:gap-4 sm:text-xl xl:min-h-22 xl:gap-5 xl:px-5 xl:text-[22px]"
                        :class="step.answers[step.currentItem.value.id] === choice ? 'border-primary bg-primary-light text-primary shadow-lg shadow-primary/10' : 'border-blue-100 bg-surface text-text hover:border-primary'"
                        @click="choose(choice)"
                    >
                        <span
                            class="grid size-8 place-items-center rounded-full border-[3px] sm:size-9"
                            :class="step.answers[step.currentItem.value.id] === choice ? 'border-primary' : 'border-blue-200 group-hover:border-primary'"
                            aria-hidden="true"
                        >
                            <span v-if="step.answers[step.currentItem.value.id] === choice" class="size-5 rounded-full bg-primary" />
                        </span>
                        <span class="min-w-0 break-words">{{ choice }}</span>
                        <Star
                            v-if="step.answers[step.currentItem.value.id] === choice"
                            class="size-7 fill-primary text-primary sm:size-8"
                            aria-hidden="true"
                        />
                    </button>
                </div>
                <p v-if="step.feedback.value" class="mt-5 rounded-2xl bg-accent px-4 py-3 text-base font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check answers' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
