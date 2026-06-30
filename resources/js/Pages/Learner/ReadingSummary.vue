<script setup>
import { computed } from 'vue';
import { ArrowRight, BookOpen, Brain, Check, Flag, Star, Target, Trophy } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';

const props = defineProps({ attempt: Object });

const evaluatorMessage = 'I used your final reading score to find your reading level. Tap continue when you are ready to see your path.';

const percentWidth = (value) => {
    const numericValue = Number(value ?? 0);
    return `${Math.min(Math.max(numericValue, 0), 100)}%`;
};

const scoreMetrics = computed(() => [
    {
        label: 'Incorrect Words',
        value: props.attempt?.incorrect_words ?? '-',
        icon: BookOpen,
        tone: 'blue',
    },
    {
        label: 'Accuracy',
        value: props.attempt?.reading_accuracy ?? '-',
        suffix: '%',
        icon: Target,
        tone: 'green',
        progress: props.attempt?.reading_accuracy,
    },
    {
        label: 'Comprehension',
        value: props.attempt?.comprehension_percentage ?? '-',
        suffix: '%',
        icon: Brain,
        tone: 'violet',
        progress: props.attempt?.comprehension_percentage,
    },
    {
        label: 'Final Reading Score',
        value: props.attempt?.final_reading_score ?? '-',
        icon: Star,
        tone: 'amber',
    },
]);
</script>

<template>
    <GuideLayout
        :progress="94"
        diagnostic-step="sentence-reading"
        layout="stacked"
        max-width="72rem"
        agent-type="evaluator"
        agent-state="celebrating"
        :agent-message="evaluatorMessage"
        agent-line-key="estelle.result.reading_summary"
        eyebrow="All Steps Completed"
        divider-label="Reading result"
        primary-label="See My Path"
        primary-href="/learner/diagnostic/module-placement"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Reading Check <span class="guide-title-accent">Complete</span>
        </template>

        <section class="reading-summary-shell">
            <div class="guide-progress-card guide-anim reading-summary-hero" style="--guide-delay: 200ms">
                <span class="guide-pill">
                    <Check class="size-4" />
                    Great job
                </span>
                <p class="reading-summary-hero-copy">
                    Here is a summary of your reading performance.
                </p>
            </div>

            <div class="reading-summary-metric-grid">
                <div
                    v-for="(metric, index) in scoreMetrics"
                    :key="metric.label"
                    class="guide-progress-card guide-anim reading-summary-metric-card"
                    :style="`--guide-delay: ${285 + index * 45}ms`"
                >
                    <span :class="['reading-summary-metric-icon', `reading-summary-metric-icon--${metric.tone}`]">
                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="guide-kicker">{{ metric.label }}</span>
                    <span class="reading-summary-metric-value">
                        {{ metric.value }}<span v-if="metric.suffix">{{ metric.suffix }}</span>
                    </span>
                    <span
                        v-if="metric.progress !== undefined"
                        class="reading-summary-progress"
                        aria-hidden="true"
                    >
                        <span
                            class="reading-summary-progress-fill"
                            :class="`reading-summary-progress-fill--${metric.tone}`"
                            :style="{ width: percentWidth(metric.progress) }"
                        />
                    </span>
                </div>
            </div>

            <div class="reading-summary-info-grid">
                <div class="guide-trait guide-anim reading-summary-info-card" style="--guide-delay: 480ms">
                    <span class="guide-trait-icon guide-trait-icon--violet"><Trophy class="size-5" /></span>
                    <div class="guide-trait-body">
                        <span class="guide-trait-label">Reading level</span>
                        <span class="reading-summary-info-heading">{{ attempt?.reading_classification ?? 'Recorded' }}</span>
                        <span class="guide-trait-desc">This is your current reading level based on your performance.</span>
                    </div>
                </div>

                <div class="guide-trait guide-anim reading-summary-info-card" style="--guide-delay: 545ms">
                    <span class="guide-trait-icon guide-trait-icon--teal"><Flag class="size-5" /></span>
                    <div class="guide-trait-body">
                        <span class="guide-trait-label">How it works</span>
                        <span class="guide-trait-desc">
                            Accuracy comes from the passage word-error count. Reading level is based on the final reading score.
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.reading-summary-shell {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.reading-summary-hero {
    justify-items: center;
    padding: clamp(1.15rem, 3vw, 1.65rem);
    text-align: center;
}

.reading-summary-hero-copy {
    color: var(--rd-text-main);
    font-size: clamp(1.15rem, 2.5vw, 1.55rem);
    font-weight: 900;
    line-height: 1.15;
}

.reading-summary-metric-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 700px) {
    .reading-summary-metric-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.reading-summary-metric-card {
    justify-items: center;
    min-height: 11.5rem;
    padding: 1.05rem 0.95rem 1.15rem;
    text-align: center;
}

.reading-summary-metric-icon {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border-radius: 1rem;
}

.reading-summary-metric-icon--blue {
    background: #eff6ff;
    color: #2563eb;
}

.reading-summary-metric-icon--green {
    background: #ecfdf5;
    color: #059669;
}

.reading-summary-metric-icon--violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.reading-summary-metric-icon--amber {
    background: #fffbeb;
    color: #d97706;
}

.reading-summary-metric-value {
    color: var(--rd-text-main);
    font-size: clamp(2rem, 4vw, 2.65rem);
    font-weight: 900;
    line-height: 1;
}

.reading-summary-metric-value span {
    color: var(--rd-text-muted);
    font-size: 0.48em;
}

.reading-summary-progress {
    display: block;
    width: min(100%, 9rem);
    height: 0.55rem;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(54, 83, 101, 0.1);
    box-shadow: inset 0 1px 2px rgba(54, 83, 101, 0.14);
}

.reading-summary-progress-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
}

.reading-summary-progress-fill--green {
    background: #10b981;
}

.reading-summary-progress-fill--violet {
    background: #8b5cf6;
}

.reading-summary-info-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 760px) {
    .reading-summary-info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.reading-summary-info-card {
    align-items: flex-start;
    text-align: left;
}

.reading-summary-info-heading {
    color: var(--rd-text-main);
    font-size: 1.12rem;
    font-weight: 900;
    line-height: 1.16;
}
</style>
