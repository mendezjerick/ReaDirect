<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HelpCircle, Star } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    questions: Array,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});

const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const normalizeChoiceKey = (question, answer) => {
    const normalized = String(answer ?? '').trim().toUpperCase();
    if (['A', 'B', 'C', 'D'].includes(normalized)) return normalized;

    const selected = Object.entries(question.choices ?? {}).find(([, choice]) => String(choice).trim().toLowerCase() === String(answer ?? '').trim().toLowerCase());
    return selected?.[0] ?? answer;
};
const savedAnswers = Object.fromEntries((props.questions ?? []).filter((question) => question.saved_answer).map((question) => [question.id, normalizeChoiceKey(question, question.saved_answer)]));
const firstUnansweredIndex = (props.questions ?? []).findIndex((question) => !savedAnswers[question.id]);
const step = useStepAssessment(props.questions, {
    emptyMessage: 'Choose one answer before moving on.',
    initialIndex: firstUnansweredIndex === -1 ? Math.max((props.questions ?? []).length - 1, 0) : firstUnansweredIndex,
});
const form = useForm({ assessment_attempt_id: props.assessmentAttemptId, responses: [] });
Object.entries(savedAnswers).forEach(([id, answer]) => {
    step.answers[id] = answer;
});

const choose = (choiceKey) => {
    step.answers[step.currentItem.value.id] = choiceKey;
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
            answer: choiceKey,
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
    <GuideLayout
        :progress="86"
        align="start"
        eyebrow="Comprehension"
        divider-label="Story question"
        agent-message="Choose the best answer based on the story you read."
        agent-line-key="vivian.assessment.comprehension_choice"
        :primary-label="step.isLast.value ? 'Finish final check' : 'Next'"
        :primary-disabled="form.processing"
        @primary="handlePrimary"
    >
        <template v-if="canUseDeveloperJumpControls && !step.isFirst.value" #secondary-action>
            <SecondaryButton @click="step.goBack">Developer QA: Back</SecondaryButton>
        </template>

        <template #title>
            Story <span class="guide-title-accent">Questions</span>
        </template>

        <div class="guide-progress-card guide-anim" style="--guide-delay: 200ms">
            <div class="guide-progress-meta">
                <span class="guide-pill">Question {{ step.currentIndex.value + 1 }} of {{ questions.length }}</span>
                <span class="guide-pill guide-pill--muted">Choose one answer</span>
            </div>
            <div class="guide-progress-track" aria-hidden="true">
                <span class="guide-progress-fill" :style="{ width: `${step.progressPercent.value}%` }" />
            </div>
        </div>

        <div :key="step.currentItem.value.id" class="guide-question-card guide-anim" style="--guide-delay: 285ms">
            <div class="guide-question-header">
                <span class="guide-question-icon"><HelpCircle class="size-7 stroke-[2.5]" /></span>
                <p class="guide-question-text">{{ step.currentItem.value.question_text }}</p>
            </div>

            <div class="guide-choice-grid guide-choice-grid--two">
                <button
                    v-for="(choice, key) in step.currentItem.value.choices"
                    :key="key"
                    type="button"
                    class="guide-choice"
                    :class="{ 'guide-choice--selected': step.answers[step.currentItem.value.id] === key }"
                    @click="choose(key)"
                >
                    <span class="guide-choice-dot" aria-hidden="true" />
                    <span class="min-w-0 break-words">{{ key }}. {{ choice }}</span>
                    <Star
                        v-if="step.answers[step.currentItem.value.id] === key"
                        class="size-5 fill-primary text-primary"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <p v-if="step.feedback.value" class="guide-status guide-status--warning mt-4">
                {{ step.feedback.value }}
            </p>
        </div>
    </GuideLayout>
</template>
