<script setup>
import { computed } from 'vue';
import { ArrowRight, BookOpen, Check, Clock3, Music, Shapes, Star } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';

const props = defineProps({
    attempt: Object,
    placementPreview: Object,
    taskTwoBReview: Object,
    passageEligible: Boolean,
});

const topMetrics = computed(() => [
    {
        label: 'Task 1 Letters',
        value: props.attempt?.task_1_score ?? '-',
        icon: Shapes,
        tone: 'blue',
    },
    {
        label: 'Task 2A Rhymes',
        value: props.attempt?.task_2a_score ?? '-',
        icon: Music,
        tone: 'violet',
    },
    {
        label: 'Task 2B Words',
        value: props.attempt?.task_2b_score ?? '-',
        icon: BookOpen,
        tone: 'green',
    },
]);

const crlaScore = computed(() => props.attempt?.crla_total_score ?? '-');
const crlaClassification = computed(() => props.attempt?.crla_classification ?? 'Recorded');
const nextHref = computed(() => props.passageEligible ? '/learner/diagnostic/reading-intro' : '/learner/diagnostic/module-placement');
const nextLabel = computed(() => props.passageEligible ? 'Continue to Passage Reading' : 'Continue to Module Placement');

const evaluatorMessage = computed(() => {
    return props.passageEligible
        ? 'The CRLA tasks are complete. Review your scores first, then you will continue with a short reading passage.'
        : 'The CRLA tasks are complete. Passage reading is not needed for this result, so we can move forward.';
});

const evaluatorLineKey = computed(() => props.passageEligible
    ? 'estelle.result.crla.summary_with_passage'
    : 'estelle.result.crla.summary_no_passage');

const accuracyTone = (percentage) => {
    const score = Number(percentage ?? 0);

    if (score >= 90) return 'crla-breakdown-score--high';
    if (score >= 75) return 'crla-breakdown-score--good';
    if (score >= 60) return 'crla-breakdown-score--watch';
    return 'crla-breakdown-score--low';
};
</script>

<template>
    <GuideLayout
        :progress="65"
        diagnostic-step="task-2b"
        layout="stacked"
        max-width="72rem"
        agent-type="evaluator"
        agent-state="encouraging"
        :agent-message="evaluatorMessage"
        :agent-line-key="evaluatorLineKey"
        eyebrow="CRLA Complete"
        divider-label="Summary result"
        :primary-label="nextLabel"
        :primary-href="nextHref"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Your <span class="guide-title-accent">CRLA</span> Score Is Ready
        </template>

        <section class="crla-summary-shell">
            <div class="guide-progress-card guide-anim crla-total-card" style="--guide-delay: 200ms">
                <span class="guide-pill crla-total-pill">
                    <Star class="size-4 fill-current" />
                    Foundational tasks complete
                </span>

                <div class="crla-total-score">
                    {{ crlaScore }}<span>/30</span>
                </div>
                <p class="crla-total-classification">{{ crlaClassification }}</p>
                <p class="crla-total-copy">You completed the foundational reading tasks.</p>
            </div>

            <div class="crla-metric-grid">
                <div
                    v-for="(metric, index) in topMetrics"
                    :key="metric.label"
                    class="guide-progress-card guide-anim crla-metric-card"
                    :style="`--guide-delay: ${285 + index * 45}ms`"
                >
                    <span :class="['crla-metric-icon', `crla-metric-icon--${metric.tone}`]">
                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="crla-metric-value">{{ metric.value }}</span>
                    <span class="guide-kicker">{{ metric.label }}</span>
                </div>
            </div>

            <div class="crla-info-grid">
                <div class="guide-trait guide-anim crla-info-card" style="--guide-delay: 430ms">
                    <span class="guide-trait-icon guide-trait-icon--violet"><Clock3 class="size-5" /></span>
                    <div class="guide-trait-body">
                        <span class="guide-trait-label">What we noticed</span>
                        <span class="crla-info-heading">{{ taskTwoBReview?.feedback_label ?? 'Standard' }}</span>
                        <span class="guide-trait-desc">{{ placementPreview?.crla_meaning ?? 'Your performance was carefully recorded.' }}</span>
                    </div>
                </div>

                <div class="guide-trait guide-anim crla-info-card" style="--guide-delay: 500ms">
                    <span class="guide-trait-icon guide-trait-icon--green"><Check class="size-5" /></span>
                    <div class="guide-trait-body">
                        <span class="guide-trait-label">Decision reason</span>
                        <span class="guide-trait-desc">{{ placementPreview?.decision_reason ?? 'Completing the tasks successfully.' }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="taskTwoBReview?.items?.length"
                class="guide-question-card guide-anim crla-breakdown"
                style="--guide-delay: 570ms"
            >
                <div class="guide-question-header crla-breakdown-header">
                    <span class="guide-question-icon"><BookOpen class="size-7 stroke-[2.5]" /></span>
                    <div>
                        <p class="guide-kicker">Task 2B Breakdown</p>
                        <p class="crla-breakdown-title">Word accuracy check</p>
                    </div>
                    <span class="crla-breakdown-average">
                        {{ taskTwoBReview.average_accuracy_percentage }}% Avg
                    </span>
                </div>

                <div class="crla-breakdown-list">
                    <div
                        v-for="item in taskTwoBReview.items"
                        :key="item.item_number"
                        class="crla-breakdown-item"
                    >
                        <div class="crla-breakdown-copy">
                            <span class="guide-kicker">Item {{ item.item_number }}</span>
                            <span class="crla-breakdown-prompt">{{ item.prompt }}</span>
                        </div>
                        <span :class="['crla-breakdown-score', accuracyTone(item.accuracy_percentage)]">
                            {{ item.accuracy_percentage }}%
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.crla-summary-shell {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.crla-total-card {
    justify-items: center;
    overflow: hidden;
    padding: clamp(1.2rem, 3vw, 1.75rem);
    text-align: center;
}

.crla-total-pill {
    justify-self: center;
}

.crla-total-score {
    color: var(--rd-text-main);
    font-size: clamp(3.4rem, 9vw, 5.5rem);
    font-weight: 900;
    line-height: 0.95;
}

.crla-total-score span {
    color: rgba(54, 83, 101, 0.25);
    font-size: 0.45em;
}

.crla-total-classification {
    color: var(--rd-text-main);
    font-size: clamp(1.15rem, 2.5vw, 1.55rem);
    font-weight: 900;
    line-height: 1.15;
}

.crla-total-copy {
    color: var(--rd-text-muted);
    font-size: 0.88rem;
    font-weight: 800;
    line-height: 1.35;
}

.crla-metric-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 700px) {
    .crla-metric-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.crla-metric-card {
    justify-items: center;
    padding: 1.1rem 1rem 1.2rem;
    text-align: center;
}

.crla-metric-icon {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border-radius: 1rem;
}

.crla-metric-icon--blue {
    background: #eff6ff;
    color: #2563eb;
}

.crla-metric-icon--violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.crla-metric-icon--green {
    background: #ecfdf5;
    color: #059669;
}

.crla-metric-value {
    color: var(--rd-text-main);
    font-size: clamp(2rem, 4vw, 2.7rem);
    font-weight: 900;
    line-height: 1;
}

.crla-info-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 780px) {
    .crla-info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.crla-info-card {
    align-items: flex-start;
    text-align: left;
}

.crla-info-heading {
    color: var(--rd-text-main);
    font-size: 1.12rem;
    font-weight: 900;
    line-height: 1.16;
    text-transform: capitalize;
}

.crla-breakdown {
    text-align: left;
}

.crla-breakdown-header {
    align-items: center;
}

.crla-breakdown-title {
    color: var(--rd-text-main);
    font-size: clamp(1.1rem, 2.3vw, 1.45rem);
    font-weight: 900;
    line-height: 1.15;
}

.crla-breakdown-average {
    margin-left: auto;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.12);
    padding: 0.4rem 0.75rem;
    color: var(--rd-primary-orange);
    font-size: 0.78rem;
    font-weight: 900;
    line-height: 1;
    white-space: nowrap;
}

.crla-breakdown-list {
    display: grid;
    gap: 0.65rem;
}

.crla-breakdown-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.85rem;
    border: 2px solid rgba(54, 83, 101, 0.1);
    border-radius: 18px;
    background: #fff;
    padding: 0.85rem 0.95rem;
    box-shadow: 0 8px 16px rgba(54, 83, 101, 0.07);
}

.crla-breakdown-copy {
    display: grid;
    min-width: 0;
    gap: 0.2rem;
}

.crla-breakdown-prompt {
    color: var(--rd-text-main);
    font-size: 0.95rem;
    font-weight: 900;
    line-height: 1.2;
}

.crla-breakdown-score {
    border-radius: 999px;
    border: 1.5px solid currentColor;
    padding: 0.36rem 0.7rem;
    font-size: 0.84rem;
    font-weight: 900;
    line-height: 1;
}

.crla-breakdown-score--high {
    background: #ecfdf5;
    color: #047857;
}

.crla-breakdown-score--good {
    background: #eff6ff;
    color: #2563eb;
}

.crla-breakdown-score--watch {
    background: #fffbeb;
    color: #b45309;
}

.crla-breakdown-score--low {
    background: #fff1f2;
    color: #be123c;
}

@media (max-width: 560px) {
    .crla-breakdown-header,
    .crla-breakdown-item {
        align-items: flex-start;
        grid-template-columns: 1fr;
    }

    .crla-breakdown-average {
        margin-left: 0;
    }
}
</style>
