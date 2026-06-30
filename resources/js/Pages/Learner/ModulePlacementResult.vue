<script setup>
import { computed } from 'vue';
import {
    ArrowRight,
    BookOpen,
    BrainCircuit,
    Check,
    FileText,
    MessageCircle,
    Percent,
    Shapes,
    Sparkles,
    Star,
    Target,
    Trophy,
} from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';

const props = defineProps({ attempt: Object, decision: Object, module: Object });

const moduleTitle = computed(() => props.module?.title ?? 'Reading at Grade Level');
const decisionReason = computed(() => props.decision?.decision_reason ?? 'Your reading path is ready.');
const crlaMeaning = computed(() => props.decision?.crla_meaning ?? 'Your foundational reading skills were recorded.');
const readingMeaning = computed(() => props.decision?.reading_meaning ?? 'Your passage reading level was recorded.');
const placementExplanation = computed(() => props.decision?.placement_explanation ?? 'Your scores were used to choose the next reading path.');
const ruleApplied = computed(() => props.decision?.rule_applied ?? 'standard placement');

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
        icon: MessageCircle,
        tone: 'violet',
    },
    {
        label: 'Task 2B Words',
        value: props.attempt?.task_2b_score ?? '-',
        icon: FileText,
        tone: 'green',
    },
    {
        label: 'CRLA Total',
        value: props.attempt?.crla_total_score ?? '-',
        icon: Star,
        tone: 'amber',
    },
]);

const bottomMetrics = computed(() => [
    {
        label: 'Passage Accuracy',
        value: props.attempt?.reading_accuracy ?? '-',
        suffix: '%',
        icon: Percent,
        tone: 'cyan',
    },
    {
        label: 'Comprehension',
        value: props.attempt?.comprehension_percentage ?? '-',
        suffix: '%',
        icon: BrainCircuit,
        tone: 'pink',
    },
    {
        label: 'Reading Score',
        value: props.attempt?.final_reading_score ?? '-',
        suffix: '%',
        icon: Target,
        tone: 'indigo',
    },
]);

const evaluatorMessage = computed(() => {
    if (props.module) {
        return 'Great job. Your reading path is ready, and it will guide the next activities on your dashboard.';
    }

    return 'Wonderful work. You are reading at grade level, so you can continue to your dashboard.';
});

const evaluatorLineKey = computed(() => props.module
    ? 'estelle.result.module_placement'
    : 'estelle.result.grade_level_placement');
</script>

<template>
    <GuideLayout
        :progress="100"
        diagnostic-step="sentence-reading"
        layout="stacked"
        max-width="72rem"
        agent-type="evaluator"
        agent-state="celebrating"
        :agent-message="evaluatorMessage"
        :agent-line-key="evaluatorLineKey"
        eyebrow="Path Ready"
        divider-label="Placement result"
        primary-label="Continue to My Dashboard"
        primary-href="/learner/dashboard"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Your Reading <span class="guide-title-accent">Path</span> Is Ready
        </template>

        <section class="module-placement-shell">
            <div class="guide-progress-card guide-anim module-placement-path-card" style="--guide-delay: 200ms">
                <span class="guide-pill">
                    <Check class="size-4" />
                    Assigned path
                </span>

                <h2 class="module-placement-path-title">{{ moduleTitle }}</h2>
                <p class="module-placement-path-copy">{{ decisionReason }}</p>
            </div>

            <div class="module-placement-top-grid">
                <div
                    v-for="(metric, index) in topMetrics"
                    :key="metric.label"
                    class="guide-progress-card guide-anim module-placement-metric-card"
                    :style="`--guide-delay: ${285 + index * 40}ms`"
                >
                    <span :class="['module-placement-metric-icon', `module-placement-metric-icon--${metric.tone}`]">
                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="module-placement-metric-value">{{ metric.value }}</span>
                    <span class="guide-kicker">{{ metric.label }}</span>
                </div>
            </div>

            <div class="module-placement-bottom-grid">
                <div
                    v-for="(metric, index) in bottomMetrics"
                    :key="metric.label"
                    class="guide-progress-card guide-anim module-placement-wide-metric"
                    :style="`--guide-delay: ${445 + index * 45}ms`"
                >
                    <span :class="['module-placement-metric-icon', `module-placement-metric-icon--${metric.tone}`]">
                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="module-placement-wide-copy">
                        <span class="module-placement-metric-value">
                            {{ metric.value }}<span v-if="metric.suffix">{{ metric.suffix }}</span>
                        </span>
                        <span class="guide-kicker">{{ metric.label }}</span>
                    </span>
                </div>
            </div>

            <div class="module-placement-info-grid">
                <div class="guide-trait guide-anim module-placement-info-card" style="--guide-delay: 580ms">
                    <span class="guide-trait-icon guide-trait-icon--teal"><BookOpen class="size-5" /></span>
                    <div class="guide-trait-body">
                        <span class="guide-trait-label">CRLA level</span>
                        <span class="module-placement-info-heading">{{ attempt?.crla_classification ?? 'Recorded' }}</span>
                        <span class="guide-trait-desc">{{ crlaMeaning }}</span>
                    </div>
                </div>

                <div class="guide-trait guide-anim module-placement-info-card" style="--guide-delay: 650ms">
                    <span class="guide-trait-icon guide-trait-icon--violet"><Trophy class="size-5" /></span>
                    <div class="guide-trait-body">
                        <span class="guide-trait-label">Reading level</span>
                        <span class="module-placement-info-heading">{{ attempt?.reading_classification ?? 'Recorded' }}</span>
                        <span class="guide-trait-desc">{{ readingMeaning }}</span>
                    </div>
                </div>
            </div>

            <div class="guide-question-card guide-anim module-placement-reason" style="--guide-delay: 720ms">
                <div class="guide-question-header">
                    <span class="guide-question-icon"><Sparkles class="size-7 stroke-[2.5]" /></span>
                    <div>
                        <p class="guide-kicker">Why this path?</p>
                        <p class="module-placement-reason-title">Placement explanation</p>
                    </div>
                </div>

                <p class="module-placement-reason-copy">{{ placementExplanation }}</p>
                <span class="module-placement-rule">Rule applied: {{ ruleApplied }}</span>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.module-placement-shell {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.module-placement-path-card {
    justify-items: center;
    overflow: hidden;
    padding: clamp(1.25rem, 3vw, 1.8rem);
    text-align: center;
}

.module-placement-path-title {
    color: var(--rd-text-main);
    font-size: clamp(1.55rem, 3.8vw, 2.35rem);
    font-weight: 900;
    line-height: 1.05;
}

.module-placement-path-copy {
    max-width: 48rem;
    color: var(--rd-text-muted);
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.35;
}

.module-placement-top-grid,
.module-placement-bottom-grid,
.module-placement-info-grid {
    display: grid;
    gap: 0.85rem;
}

@media (min-width: 700px) {
    .module-placement-top-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .module-placement-bottom-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .module-placement-info-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.module-placement-metric-card {
    justify-items: center;
    padding: 1.05rem 0.95rem 1.15rem;
    text-align: center;
}

.module-placement-wide-metric {
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    text-align: left;
}

.module-placement-wide-copy {
    display: grid;
    min-width: 0;
    gap: 0.2rem;
}

.module-placement-metric-icon {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border-radius: 1rem;
}

.module-placement-metric-icon--blue {
    background: #eff6ff;
    color: #2563eb;
}

.module-placement-metric-icon--violet {
    background: #f5f3ff;
    color: #7c3aed;
}

.module-placement-metric-icon--green {
    background: #ecfdf5;
    color: #059669;
}

.module-placement-metric-icon--amber {
    background: #fffbeb;
    color: #d97706;
}

.module-placement-metric-icon--cyan {
    background: #ecfeff;
    color: #0891b2;
}

.module-placement-metric-icon--pink {
    background: #fdf2f8;
    color: #db2777;
}

.module-placement-metric-icon--indigo {
    background: #eef2ff;
    color: #4f46e5;
}

.module-placement-metric-value {
    color: var(--rd-text-main);
    font-size: clamp(2rem, 4vw, 2.6rem);
    font-weight: 900;
    line-height: 1;
}

.module-placement-metric-value span {
    color: var(--rd-text-muted);
    font-size: 0.5em;
}

.module-placement-info-card {
    align-items: flex-start;
    text-align: left;
}

.module-placement-info-heading {
    color: var(--rd-text-main);
    font-size: 1.12rem;
    font-weight: 900;
    line-height: 1.16;
}

.module-placement-reason {
    text-align: left;
}

.module-placement-reason-title {
    color: var(--rd-text-main);
    font-size: clamp(1.1rem, 2.3vw, 1.45rem);
    font-weight: 900;
    line-height: 1.15;
}

.module-placement-reason-copy {
    color: var(--rd-text-muted);
    font-size: 0.95rem;
    font-weight: 800;
    line-height: 1.45;
}

.module-placement-rule {
    display: inline-flex;
    width: fit-content;
    border-radius: 0.7rem;
    background: rgba(245, 133, 73, 0.12);
    padding: 0.5rem 0.75rem;
    color: var(--rd-primary-orange);
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
</style>
