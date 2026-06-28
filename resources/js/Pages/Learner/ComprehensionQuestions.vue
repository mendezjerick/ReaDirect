<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { HelpCircle, Star, PenTool, BookOpen, Flag } from 'lucide-vue-next';
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
    <LearnerLayout :progress="86" diagnostic-step="sentence-reading" :has-bottom-bar="false">
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

            <!-- Header & Progress -->
            <div class="mb-3 flex items-center justify-between gap-3 sm:mb-4">
                <div class="assessment-progress-track flex-1 max-w-[600px] xl:max-w-[700px]">
                    <div class="assessment-progress-face">
                        <span class="assessment-progress-marker assessment-progress-marker--start" aria-hidden="true">
                            <BookOpen class="size-4" stroke-width="2.7" />
                        </span>
                        <div class="assessment-progress-fill" :style="{ width: `${step.progressPercent.value}%` }" />
                        <span class="assessment-progress-marker assessment-progress-marker--end" aria-hidden="true">
                            <Flag class="size-4" stroke-width="2.7" />
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-primary sm:text-xs">
                        <PenTool class="size-3" />
                        Comprehension
                    </span>
                </div>
            </div>

            <!-- Question card -->
            <div :key="step.currentItem.value.id" class="anim-card relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-5 shadow-2xl shadow-primary/10 sm:p-6 xl:p-7">
                <!-- Question header -->
                <div class="mb-4 flex items-start gap-3 sm:items-center xl:mb-5 xl:gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20 xl:h-14 xl:w-14">
                        <HelpCircle class="size-6 stroke-[2.5] xl:size-8" />
                    </span>
                    <p class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-xl font-black leading-tight text-transparent sm:text-2xl xl:text-3xl">
                        {{ step.currentItem.value.question_text }}
                    </p>
                </div>

                <!-- Answer choices -->
                <div class="anim-stagger grid gap-2.5 sm:grid-cols-2 xl:gap-3">
                    <button
                        v-for="(choice, key) in step.currentItem.value.choices"
                        :key="key"
                        type="button"
                        class="choice-btn group grid min-h-12 grid-cols-[32px_1fr_auto] items-center gap-2.5 rounded-[20px] border-2 px-4 py-2.5 text-left text-base font-black transition-all duration-200 sm:min-h-14 sm:grid-cols-[38px_1fr_auto] sm:gap-3 sm:text-lg xl:min-h-16 xl:gap-4 xl:px-5 xl:text-xl"
                        :class="step.answers[step.currentItem.value.id] === key
                            ? 'border-primary bg-primary/5 text-primary shadow-xl shadow-primary/10 ring-1 ring-primary/20'
                            : 'border-slate-200/80 bg-white text-slate-800 shadow-xl shadow-slate-200/30 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/10'"
                        @click="choose(key)"
                    >
                        <span
                            class="grid size-7 place-items-center rounded-full border-[3px] transition-all duration-200 sm:size-8"
                            :class="step.answers[step.currentItem.value.id] === key ? 'border-primary' : 'border-slate-200 group-hover:border-primary/50'"
                            aria-hidden="true"
                        >
                            <span v-if="step.answers[step.currentItem.value.id] === key" class="size-4 rounded-full bg-gradient-to-br from-primary to-blue-600" />
                        </span>
                        <span class="min-w-0 break-words">{{ key }}. {{ choice }}</span>
                        <Star
                            v-if="step.answers[step.currentItem.value.id] === key"
                            class="size-6 fill-primary text-primary sm:size-7"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <!-- Feedback -->
                <p v-if="step.feedback.value" class="mt-5 rounded-[20px] bg-amber-50 px-4 py-3 text-[14px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                    {{ step.feedback.value }}
                </p>
            </div>
            
            <div class="mt-2 flex w-full items-center justify-between gap-3 sm:mt-4">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check answers' : 'Next' }}
                </PrimaryButton>
            </div>
        </section>
    </LearnerLayout>
</template>

<style scoped>
/* Card spring entrance */
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Content pop (for large text/letters) */
.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}

/* Header fade down */
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Panel slide up */
.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Staggered children */
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Choice button hover lift */
.choice-btn {
    transform: translateY(0);
}
.choice-btn:hover {
    transform: translateY(-1px);
}
.choice-btn:active {
    transform: translateY(0);
}

.assessment-progress-track {
    position: relative;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: 26px;
    background: var(--rd-story-surface);
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
    padding: 8px 14px 12px;
    height: clamp(2.75rem, 6dvh, 4.6rem);
}
.assessment-progress-face {
    position: relative;
    display: flex;
    height: 100%;
    min-height: 0;
    align-items: stretch;
    overflow: hidden;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 18px;
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}
.assessment-progress-marker {
    position: absolute;
    z-index: 3;
    top: 50%;
    display: grid;
    width: clamp(2.1rem, 4.5dvh, 3.1rem);
    height: clamp(2.1rem, 4.5dvh, 3.1rem);
    place-items: center;
    border: 2px solid rgba(238, 193, 112, 0.7);
    border-radius: 999px;
    background: #FFFDF7;
    color: var(--rd-brown);
    transform: translateY(-50%);
    pointer-events: none;
    box-shadow: 0 3px 0 rgba(111, 101, 52, 0.18), 0 6px 12px rgba(54, 83, 101, 0.12);
}
.assessment-progress-marker--start {
    left: 0.35rem;
    color: var(--rd-brown);
}
.assessment-progress-marker--end {
    right: 0.35rem;
    color: var(--rd-brown);
}
.assessment-progress-marker--end svg {
    fill: rgba(238, 193, 112, 0.75);
}
.assessment-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #F58549 0%, #F2A65A 100%);
    box-shadow: inset 0 2px 0 rgba(255, 255, 255, 0.24), 0 4px 10px rgba(245, 133, 73, 0.18);
    transition: width 240ms ease;
}
</style>
