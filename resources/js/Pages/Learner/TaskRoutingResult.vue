<script setup>
import { computed } from 'vue';
import { CheckCircle2, ChevronRight, Sparkles, XCircle } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';

const props = defineProps({
    attempt: Object,
    route: Object,
    itemResponses: {
        type: Array,
        default: () => [],
    },
});

const score = computed(() => props.attempt?.task_1_score ?? 0);
const total = 10;
const requiresTask2A = computed(() => props.route?.requires_task_2a ?? score.value < 7);
const nextHref = computed(() => requiresTask2A.value ? '/learner/diagnostic/task-2a' : '/learner/diagnostic/task-2b');
const nextLabel = computed(() => requiresTask2A.value ? 'Continue to Task 2A' : 'Continue to Task 2B');
const nextTitle = computed(() => requiresTask2A.value ? 'Task 2A: Rhyme Recognition' : 'Task 2B: Word in Sentence');
const agentMessage = 'You finished the first reading task. Your score helps us decide which reading activity should come next.';

const hasItemResults = computed(() => Array.isArray(props.itemResponses) && props.itemResponses.length > 0);
const correctCount = computed(() => props.itemResponses.filter((response) => response.is_correct).length);
const incorrectCount = computed(() => props.itemResponses.filter((response) => !response.is_correct).length);

const radius = 45;
const circumference = 2 * Math.PI * radius;
const scorePercentage = computed(() => (score.value / total) * 100);
const strokeDashoffset = computed(() => circumference - (scorePercentage.value / 100) * circumference);

const scoreTheme = computed(() => {
    if (score.value >= 7) {
        return { gradient: 'url(#score-gradient-high)', tone: 'Strong start' };
    }

    if (score.value >= 4) {
        return { gradient: 'url(#score-gradient-med)', tone: 'Keep practicing' };
    }

    return { gradient: 'url(#score-gradient-low)', tone: 'More practice ahead' };
});
</script>

<template>
    <GuideLayout
        :progress="30"
        diagnostic-step="task-1"
        layout="stacked"
        max-width="72rem"
        agent-type="evaluator"
        agent-state="encouraging"
        :agent-message="agentMessage"
        agent-line-key="estelle.result.task1.routing"
        eyebrow="Task 1 Complete"
        divider-label="Routing result"
        :primary-label="nextLabel"
        :primary-href="nextHref"
    >
        <template #primary-icon>
            <ChevronRight class="size-5" />
        </template>

        <template #title>
            Letter <span class="guide-title-accent">Pronunciation</span>
        </template>

        <div class="guide-progress-card guide-anim" style="--guide-delay: 200ms">
            <div class="task-routing-score">
                <div class="task-routing-score-ring" aria-label="Task 1 score">
                    <svg class="task-routing-score-svg" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(54, 83, 101, 0.12)" stroke-width="8" stroke-linecap="round" />
                        <circle
                            cx="50"
                            cy="50"
                            r="45"
                            fill="none"
                            :stroke="scoreTheme.gradient"
                            stroke-width="8"
                            stroke-linecap="round"
                            class="task-routing-score-arc"
                            :stroke-dasharray="circumference"
                            :stroke-dashoffset="strokeDashoffset"
                        />
                        <defs>
                            <linearGradient id="score-gradient-high" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#10B981" />
                                <stop offset="100%" stop-color="#34D399" />
                            </linearGradient>
                            <linearGradient id="score-gradient-med" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#F59E0B" />
                                <stop offset="100%" stop-color="#F2A65A" />
                            </linearGradient>
                            <linearGradient id="score-gradient-low" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#EF4444" />
                                <stop offset="100%" stop-color="#F97316" />
                            </linearGradient>
                        </defs>
                    </svg>

                    <div class="task-routing-score-copy">
                        <span class="task-routing-score-value">{{ score }}</span>
                        <span class="task-routing-score-total">out of {{ total }}</span>
                    </div>
                </div>

                <div class="task-routing-score-body">
                    <span class="guide-pill">
                        <Sparkles class="size-4" />
                        {{ scoreTheme.tone }}
                    </span>
                    <p class="task-routing-score-title">You answered {{ score }} out of {{ total }} letters correctly.</p>
                    <p class="task-routing-score-desc">Miss Estelle uses this score to choose the reading task that fits next.</p>
                </div>
            </div>
        </div>

        <div v-if="hasItemResults" class="guide-question-card guide-anim" style="--guide-delay: 285ms">
            <div class="guide-question-header">
                <span class="guide-question-icon"><CheckCircle2 class="size-7 stroke-[2.5]" /></span>
                <div>
                    <p class="guide-kicker">Your Letters</p>
                    <p class="task-routing-section-title">Quick letter check</p>
                </div>
            </div>

            <div class="task-routing-letter-grid">
                <div
                    v-for="(item, index) in itemResponses"
                    :key="index"
                    class="task-routing-letter"
                    :class="item.is_correct ? 'task-routing-letter--correct' : 'task-routing-letter--incorrect'"
                    :style="`--guide-delay: ${330 + index * 30}ms`"
                >
                    <span>{{ item.letter }}</span>
                </div>
            </div>

            <div class="task-routing-counts">
                <span class="task-routing-count task-routing-count--correct">
                    <CheckCircle2 class="size-4" />
                    {{ correctCount }} correct
                </span>
                <span v-if="incorrectCount > 0" class="task-routing-count task-routing-count--incorrect">
                    <XCircle class="size-4" />
                    {{ incorrectCount }} to practice
                </span>
            </div>
        </div>

        <div class="guide-trait guide-anim" style="--guide-delay: 370ms">
            <span class="guide-trait-icon guide-trait-icon--teal"><ChevronRight class="size-5" /></span>
            <div class="guide-trait-body">
                <span class="guide-trait-label">{{ nextTitle }}</span>
                <span v-if="requiresTask2A" class="guide-trait-desc">
                    Your score of {{ score }}/10 means we will now do a short rhyming activity.
                </span>
                <span v-else class="guide-trait-desc">
                    Great score. We will skip to reading sentences next.
                </span>
            </div>
        </div>
    </GuideLayout>
</template>

<style scoped>
.task-routing-score {
    display: grid;
    align-items: center;
    gap: 1.1rem;
}

@media (min-width: 640px) {
    .task-routing-score {
        grid-template-columns: auto minmax(0, 1fr);
    }
}

.task-routing-score-ring {
    position: relative;
    display: grid;
    width: 9.5rem;
    height: 9.5rem;
    place-items: center;
    justify-self: center;
}

.task-routing-score-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
    filter: drop-shadow(0 8px 16px rgba(54, 83, 101, 0.12));
}

.task-routing-score-arc {
    transition: stroke-dashoffset 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.task-routing-score-copy {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    text-align: center;
}

.task-routing-score-value {
    color: var(--rd-text-main);
    font-size: 2.6rem;
    font-weight: 900;
    line-height: 1;
}

.task-routing-score-total {
    margin-top: 0.25rem;
    color: var(--rd-text-muted);
    font-size: 0.72rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.task-routing-score-body {
    display: grid;
    gap: 0.7rem;
    min-width: 0;
}

.task-routing-score-title {
    color: var(--rd-text-main);
    font-size: clamp(1.2rem, 2.6vw, 1.7rem);
    font-weight: 900;
    line-height: 1.12;
}

.task-routing-score-desc {
    color: var(--rd-text-muted);
    font-size: 0.9rem;
    font-weight: 750;
    line-height: 1.35;
}

.task-routing-section-title {
    color: var(--rd-text-main);
    font-size: 1.2rem;
    font-weight: 900;
    line-height: 1.15;
}

.task-routing-letter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(2.55rem, 1fr));
    gap: 0.55rem;
}

.task-routing-letter {
    display: grid;
    min-height: 3.25rem;
    place-items: center;
    border: 2px solid rgba(54, 83, 101, 0.12);
    border-radius: 16px;
    background: #fff;
    color: var(--rd-text-main);
    font-size: 1.2rem;
    font-weight: 900;
    box-shadow: 0 8px 14px rgba(54, 83, 101, 0.08);
}

.task-routing-letter--correct {
    border-color: rgba(16, 185, 129, 0.34);
    background: #ecfdf5;
    color: #047857;
}

.task-routing-letter--incorrect {
    border-color: rgba(244, 63, 94, 0.28);
    background: #fff1f2;
    color: #be123c;
}

.task-routing-counts {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 1rem;
}

.task-routing-count {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.45rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 900;
}

.task-routing-count--correct {
    background: #ecfdf5;
    color: #047857;
}

.task-routing-count--incorrect {
    background: #fff1f2;
    color: #be123c;
}
</style>
