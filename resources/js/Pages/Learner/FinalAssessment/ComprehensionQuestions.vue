<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HelpCircle, Star } from 'lucide-vue-next';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
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
    <LearnerLayout :progress="86">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="assessment"
                state="speaking"
                presentation="comprehension"
                message="Choose the best answer based on the story you read."
                line-key="vivian.assessment.comprehension_choice"
            />
        </template>

        <section class="relative mx-auto grid w-full max-w-[960px] gap-4 sm:gap-5 xl:gap-6">
            <!-- Sparkle decorations -->
            <span class="pointer-events-none absolute -left-14 top-12 hidden text-4xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute -right-8 bottom-12 hidden text-3xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>

            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 top-0 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 bottom-0 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" aria-hidden="true" />

            <!-- Progress header -->
            <div class="anim-fade-down grid gap-3 px-1">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-primary/5 px-3.5 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10">
                        Question {{ step.currentIndex.value + 1 }} of {{ questions.length }}
                    </span>
                    <span class="rounded-full bg-emerald-50 px-3.5 py-1.5 text-[13px] font-black text-emerald-600 ring-1 ring-emerald-200/60">
                        📝 Comprehension
                    </span>
                </div>
                <div class="h-3.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500 ease-out" :style="{ width: `${step.progressPercent.value}%` }" />
                </div>
            </div>

            <!-- Question card -->
            <div :key="step.currentItem.value.id" class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-6 shadow-2xl shadow-primary/10 sm:p-7 xl:p-8">
                <!-- Question header -->
                <div class="mb-6 flex items-start gap-4 sm:items-center xl:mb-7 xl:gap-5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20 xl:h-16 xl:w-16">
                        <HelpCircle class="size-7 stroke-[2.5] xl:size-9" />
                    </span>
                    <p class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-2xl font-black leading-tight text-transparent sm:text-3xl xl:text-[34px]">
                        {{ step.currentItem.value.question_text }}
                    </p>
                </div>

                <!-- Answer choices -->
                <div class="anim-stagger grid gap-3 xl:gap-4">
                    <button
                        v-for="(choice, key) in step.currentItem.value.choices"
                        :key="key"
                        type="button"
                        class="choice-btn group grid min-h-16 grid-cols-[38px_1fr_auto] items-center gap-3 rounded-[20px] border-2 px-5 py-3.5 text-left text-lg font-black transition-all duration-200 sm:min-h-20 sm:grid-cols-[44px_1fr_auto] sm:gap-4 sm:text-xl xl:min-h-22 xl:gap-5 xl:px-6 xl:text-[22px]"
                        :class="step.answers[step.currentItem.value.id] === key
                            ? 'border-primary bg-primary/5 text-primary shadow-xl shadow-primary/10 ring-1 ring-primary/20'
                            : 'border-slate-200/80 bg-white text-slate-800 shadow-xl shadow-slate-200/30 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/10'"
                        @click="choose(key)"
                    >
                        <span
                            class="grid size-8 place-items-center rounded-full border-[3px] transition-all duration-200 sm:size-9"
                            :class="step.answers[step.currentItem.value.id] === key ? 'border-primary' : 'border-slate-200 group-hover:border-primary/50'"
                            aria-hidden="true"
                        >
                            <span v-if="step.answers[step.currentItem.value.id] === key" class="size-5 rounded-full bg-gradient-to-br from-primary to-blue-600" />
                        </span>
                        <span class="min-w-0 break-words">{{ key }}. {{ choice }}</span>
                        <Star
                            v-if="step.answers[step.currentItem.value.id] === key"
                            class="size-7 fill-primary text-primary sm:size-8"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <!-- Feedback -->
                <p v-if="step.feedback.value" class="mt-5 rounded-[20px] bg-amber-50 px-4 py-3 text-[14px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                    {{ step.feedback.value }}
                </p>
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
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay:   0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.choice-btn { transform: translateY(0); }
.choice-btn:hover  { transform: translateY(-1px); }
.choice-btn:active { transform: translateY(0); }
</style>
