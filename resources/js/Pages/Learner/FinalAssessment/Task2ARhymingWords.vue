<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Check, Music2, X } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    items: Array,
    initialIndex: Number,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});

const form = useForm({ assessment_attempt_id: props.assessmentAttemptId, responses: [] });
const savedAnswers = Object.fromEntries((props.items ?? [])
    .filter((item) => item?.saved_response?.answer)
    .map((item) => [item.id, item.saved_response.answer]));
const step = useStepAssessment(props.items ?? [], {
    emptyMessage: 'Choose Yes or No.',
    initialIndex: props.initialIndex ?? 0,
});
Object.entries(savedAnswers).forEach(([id, answer]) => {
    step.answers[id] = answer;
});

const currentPayload = computed(() => step.currentItem.value?.payload ?? {});
const currentAnswer = computed(() => step.answers[step.currentItem.value?.id] ?? '');
const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');
const vivianPrompt = 'Listen to both words carefully. Then choose Yes if they rhyme, or No if they do not rhyme.';

const selectAnswer = (answer) => {
    step.answers[step.currentItem.value.id] = answer;
    step.feedback.value = '';
};

const submit = () => {
    if (!step.validateComplete()) return;

    form.responses = step.payload((item, answer) => ({
        assessment_attempt_item_id: item.id,
        answer,
    }));
    form.post('/final-assessment/task-2a/submit', {
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? 'Choose Yes or No for each pair.';
            step.feedback.value = Array.isArray(firstError) ? firstError[0] : firstError;
        },
    });
};

const handlePrimary = () => {
    if (!step.validateCurrent()) return;
    if (step.isLast.value) return submit();
    step.goNext();
};
</script>

<template>
    <GuideLayout
        :progress="50"
        eyebrow="Task 2A"
        divider-label="Rhyme decision"
        :agent-message="vivianPrompt"
        agent-line-key="vivian.task2a.rhyme_prompt_intro"
        :primary-label="step.isLast.value ? 'Save answers' : 'Next'"
        :primary-disabled="form.processing || !step.isCurrentAnswered.value"
        @primary="handlePrimary"
    >
        <template v-if="canUseDeveloperJumpControls && !step.isFirst.value" #secondary-action>
            <SecondaryButton @click="step.goBack">Developer QA: Back</SecondaryButton>
        </template>

        <template #title>
            Do they <span class="guide-title-accent">rhyme?</span>
        </template>

        <div class="guide-progress-card guide-anim" style="--guide-delay: 200ms">
            <div class="guide-progress-meta">
                <span class="guide-pill">Pair {{ step.currentIndex.value + 1 }} of {{ items.length }}</span>
                <span class="guide-pill guide-pill--muted">Choose Yes or No</span>
            </div>
            <div class="guide-progress-track" aria-hidden="true">
                <span class="guide-progress-fill" :style="{ width: `${step.progressPercent.value}%` }" />
            </div>
        </div>

        <div class="guide-question-card guide-anim" style="--guide-delay: 285ms">
            <div class="guide-question-header">
                <span class="guide-question-icon"><Music2 class="size-6" /></span>
                <p class="guide-question-text">Listen to the word pair, then choose your answer.</p>
            </div>

            <div class="guide-word-pair">
                <div class="guide-word-row">
                    <span class="guide-word">{{ currentPayload.word_1 }}</span>
                    <span class="guide-word-divider">/</span>
                    <span class="guide-word">{{ currentPayload.word_2 }}</span>
                </div>

                <div class="guide-rhyme-options">
                    <button
                        type="button"
                        class="guide-rhyme-button guide-rhyme-button--yes"
                        :class="{ 'guide-rhyme-button--selected': currentAnswer === 'yes' }"
                        @click="selectAnswer('yes')"
                    >
                        <span class="guide-rhyme-icon"><Check class="size-6 stroke-[4]" /></span>
                        Yes
                    </button>
                    <button
                        type="button"
                        class="guide-rhyme-button guide-rhyme-button--no"
                        :class="{ 'guide-rhyme-button--selected': currentAnswer === 'no' }"
                        @click="selectAnswer('no')"
                    >
                        <span class="guide-rhyme-icon"><X class="size-6 stroke-[4]" /></span>
                        No
                    </button>
                </div>
            </div>

            <p v-if="firstFormError" class="guide-status guide-status--error mt-4">{{ firstFormError }}</p>
            <p v-if="step.feedback.value" class="guide-status guide-status--warning mt-4">{{ step.feedback.value }}</p>
        </div>
    </GuideLayout>
</template>
