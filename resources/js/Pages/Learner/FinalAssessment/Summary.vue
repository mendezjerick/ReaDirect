<script setup>
import { computed, ref } from 'vue';
import { ArrowRight, BookOpen, CheckCircle2, Shapes, Star, Target, TrendingUp } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';

const props = defineProps({ attempt: Object, comparison: Object });

const agentAction = ref('results');

const handleAgentInteractionEnded = ({ action }) => {
    if (action === 'results') {
        agentAction.value = 'congrats';
    }
};

const deltaLabel = (value) => {
    if (value === null || value === undefined) return '-';
    return value > 0 ? `+${value}` : String(value);
};

const summaryMetrics = computed(() => [
    {
        label: 'Final CRLA',
        value: props.attempt?.crla_total_score ?? '-',
        icon: Shapes,
        tone: 'blue',
    },
    {
        label: 'CRLA Growth',
        value: deltaLabel(props.comparison?.deltas?.crla_total_score),
        icon: TrendingUp,
        tone: 'green',
    },
    {
        label: 'Final Reading',
        value: props.attempt?.final_reading_score ?? '-',
        icon: BookOpen,
        tone: 'violet',
    },
    {
        label: 'Reading Growth',
        value: deltaLabel(props.comparison?.deltas?.final_reading_score),
        icon: Star,
        tone: 'amber',
    },
]);

const progressMetrics = computed(() => [
    {
        label: 'Task 1 Change',
        value: deltaLabel(props.comparison?.deltas?.task_1_score),
        icon: Shapes,
        tone: 'blue',
    },
    {
        label: 'Task 2B Change',
        value: deltaLabel(props.comparison?.deltas?.task_2b_score),
        icon: Target,
        tone: 'green',
    },
    {
        label: 'Accuracy Change',
        value: `${deltaLabel(props.comparison?.deltas?.reading_accuracy)}%`,
        icon: CheckCircle2,
        tone: 'violet',
    },
]);

const comparisonSummary = computed(() => props.comparison?.summary ?? 'Your progress was recorded.');
</script>

<template>
    <GuideLayout
        :progress="100"
        back-url="/learner/dashboard"
        back-label="Back to dashboard"
        layout="stacked"
        max-width="72rem"
        agent-type="evaluator"
        :agent-state="agentAction"
        agent-allow-congrats
        agent-message="Great job finishing your final reading check. Your effort shows what you practiced and what you can keep building next."
        agent-line-key="estelle.completion.final_check_complete"
        eyebrow="Final Check Complete"
        divider-label="Growth summary"
        primary-label="Back to Dashboard"
        primary-href="/learner/dashboard"
        @agent-interaction-ended="handleAgentInteractionEnded"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Final Check <span class="guide-title-accent">Complete</span>
        </template>

        <section class="final-summary-shell">
            <div class="final-summary-metric-grid">
                <div
                    v-for="(metric, index) in summaryMetrics"
                    :key="metric.label"
                    class="guide-progress-card guide-anim final-summary-metric-card"
                    :style="`--guide-delay: ${200 + index * 45}ms`"
                >
                    <span :class="['final-summary-metric-icon', `final-summary-metric-icon--${metric.tone}`]">
                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="final-summary-metric-value">{{ metric.value }}</span>
                    <span class="guide-kicker">{{ metric.label }}</span>
                </div>
            </div>

            <div class="guide-question-card guide-anim final-summary-progress" style="--guide-delay: 410ms">
                <div class="guide-question-header">
                    <span class="guide-question-icon"><TrendingUp class="size-7 stroke-[2.5]" /></span>
                    <div>
                        <p class="guide-kicker">Your progress</p>
                        <p class="final-summary-progress-title">What changed</p>
                    </div>
                </div>

                <p class="final-summary-copy">{{ comparisonSummary }}</p>

                <div class="final-summary-delta-grid">
                    <div
                        v-for="metric in progressMetrics"
                        :key="metric.label"
                        class="final-summary-delta-card"
                    >
                        <span :class="['final-summary-delta-icon', `final-summary-metric-icon--${metric.tone}`]">
                            <component :is="metric.icon" class="size-4 stroke-[2.5]" />
                        </span>
                        <span class="guide-kicker">{{ metric.label }}</span>
                        <span class="final-summary-delta-value">{{ metric.value }}</span>
                    </div>
                </div>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.final-summary-shell {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.final-summary-metric-grid,
.final-summary-delta-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 700px) {
    .final-summary-metric-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .final-summary-delta-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.final-summary-metric-card {
    justify-items: center;
    padding: 1.1rem 1rem 1.2rem;
    text-align: center;
}

.final-summary-metric-icon,
.final-summary-delta-icon {
    display: grid;
    place-items: center;
    border-radius: 1rem;
}

.final-summary-metric-icon {
    width: 2.75rem;
    height: 2.75rem;
}

.final-summary-delta-icon {
    width: 2.35rem;
    height: 2.35rem;
}

.final-summary-metric-icon--blue {
    background: #eff6ff;
    color: #2563eb;
}

.final-summary-metric-icon--green {
    background: #ecfdf5;
    color: #059669;
}

.final-summary-metric-icon--violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.final-summary-metric-icon--amber {
    background: #fffbeb;
    color: #d97706;
}

.final-summary-metric-value {
    color: var(--rd-text-main);
    font-size: clamp(2rem, 4vw, 2.65rem);
    font-weight: 900;
    line-height: 1;
}

.final-summary-progress {
    text-align: left;
}

.final-summary-progress-title {
    color: var(--rd-text-main);
    font-size: clamp(1.1rem, 2.3vw, 1.45rem);
    font-weight: 900;
    line-height: 1.15;
}

.final-summary-copy {
    color: var(--rd-text-muted);
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.45;
}

.final-summary-delta-grid {
    margin-top: 1rem;
}

.final-summary-delta-card {
    display: grid;
    justify-items: center;
    gap: 0.45rem;
    border: 2px solid rgba(54, 83, 101, 0.1);
    border-radius: 18px;
    background: #fff;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 8px 16px rgba(54, 83, 101, 0.07);
}

.final-summary-delta-value {
    color: var(--rd-primary-orange);
    font-size: 2rem;
    font-weight: 900;
    line-height: 1;
}
</style>
