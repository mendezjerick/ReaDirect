<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Award, BarChart3, BookOpen, CheckCircle2, Route, Star, Target, Type } from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    latestAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
    rewards: { type: Object, default: () => ({ stars: 0 }) },
});

const score = (value) => Number(value ?? 0);
const readingAccuracy = computed(() => {
    const value = props.latestAttempt?.reading_accuracy;

    if (value == null) return 0;

    const number = Number(value);

    return Math.round(number <= 1 ? number * 100 : number);
});
const totalStars = computed(() => Number(props.rewards?.stars ?? 0));
const specialStars = computed(() => Number(props.rewards?.advanced_stars ?? 0));
const diagnosticDone = computed(() => props.flowState?.diagnostic?.is_completed === true);
const overallScore = computed(() => {
    if (!diagnosticDone.value) return 0;

    return Math.round((
        score(props.latestAttempt?.task_1_score)
        + score(props.latestAttempt?.task_2b_score ?? props.latestAttempt?.task_2a_score)
        + Math.round(readingAccuracy.value / 10)
    ) / 30 * 100);
});

const progressItems = computed(() => [
    { label: 'Task 1 Letters', value: `${score(props.latestAttempt?.task_1_score)}/10`, icon: Type, detail: 'Letter recognition and sounds' },
    { label: 'Task 2A Rhymes', value: `${score(props.latestAttempt?.task_2a_score)}/10`, icon: Target, detail: 'Rhyme awareness' },
    { label: 'Task 2B Words', value: `${score(props.latestAttempt?.task_2b_score)}/10`, icon: BookOpen, detail: 'Words inside sentences' },
    { label: 'Reading Accuracy', value: `${readingAccuracy.value}%`, icon: BarChart3, detail: 'Passage reading check' },
]);
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Progress"
        subtitle="Your latest reading check details"
        active="progress"
    >
        <section class="progress-layout">
            <div class="progress-main learner-hub-panel">
                <div class="progress-summary learner-hub-face">
                    <span class="learner-hub-badge">
                        <CheckCircle2 class="size-4" stroke-width="2.8" />
                        Latest progress
                    </span>
                    <h2 class="progress-summary-title">{{ overallScore }}%</h2>
                    <p class="learner-hub-section-copy">
                        {{ diagnosticDone ? 'This is your combined diagnostic progress from the latest completed reading check.' : 'Complete the diagnostic to fill in this progress overview.' }}
                    </p>
                </div>

                <div class="progress-card-grid">
                    <article
                        v-for="item in progressItems"
                        :key="item.label"
                        class="progress-card learner-hub-card"
                    >
                        <span class="progress-card-icon">
                            <component :is="item.icon" class="size-5" stroke-width="2.8" />
                        </span>
                        <span class="progress-card-body">
                            <span class="progress-card-label">{{ item.label }}</span>
                            <span class="progress-card-value">{{ item.value }}</span>
                            <span class="progress-card-detail">{{ item.detail }}</span>
                        </span>
                    </article>
                </div>
            </div>

            <aside class="progress-side learner-hub-panel">
                <span class="learner-hub-badge">
                    <Route class="size-4" stroke-width="2.8" />
                    Current step
                </span>
                <h2 class="progress-side-title">{{ flowState?.primary_action_label ?? 'Continue' }}</h2>
                <p class="learner-hub-section-copy">
                    {{ flowState?.message ?? 'Continue your reading path from the dashboard.' }}
                </p>

                <div class="progress-star-row learner-hub-face">
                    <Star class="size-5 fill-current" stroke-width="2.8" />
                    <span>{{ totalStars }} stars earned</span>
                </div>

                <div v-if="specialStars > 0" class="progress-star-row progress-star-row--special learner-hub-face">
                    <Award class="size-5" stroke-width="2.8" />
                    <span>{{ specialStars }} special star earned</span>
                </div>

                <Link
                    :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                    class="learner-hub-primary-link progress-action"
                >
                    {{ flowState?.primary_action_label ?? 'Continue' }}
                    <ArrowRight class="size-5" stroke-width="3" />
                </Link>
            </aside>
        </section>
    </LearnerSimplePageShell>
</template>

<style scoped>
.progress-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 23rem);
    gap: 1.1rem;
    align-items: start;
}

.progress-main {
    display: grid;
    gap: 1rem;
}

.progress-summary {
    display: grid;
    gap: 0.65rem;
    padding: 1.1rem;
}

.progress-summary-title {
    color: var(--rd-primary-orange);
    font-size: clamp(3rem, 8vw, 5rem);
    font-weight: 900;
    line-height: 0.9;
}

.progress-card-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.progress-card {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.85rem;
}

.progress-card-icon {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    place-items: center;
    border: 1.5px solid rgba(245, 133, 73, 0.25);
    border-radius: 0.85rem;
    background: rgba(245, 133, 73, 0.1);
    color: var(--rd-primary-orange);
}

.progress-card-body {
    display: grid;
    min-width: 0;
    gap: 0.08rem;
}

.progress-card-label {
    color: var(--rd-text-muted);
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.progress-card-value {
    color: var(--rd-text-main);
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
}

.progress-card-detail {
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 800;
}

.progress-side {
    display: grid;
    gap: 0.9rem;
}

.progress-side-title {
    color: var(--rd-text-main);
    font-size: clamp(1.35rem, 3vw, 1.85rem);
    font-weight: 900;
    line-height: 1.1;
}

.progress-star-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.8rem;
    color: #b45309;
    font-size: 0.92rem;
    font-weight: 900;
}

.progress-star-row--special {
    border-color: rgba(30, 156, 150, 0.22);
    color: #0f766e;
}

.progress-action {
    width: 100%;
}

@media (max-width: 960px) {
    .progress-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .progress-card-grid {
        grid-template-columns: 1fr;
    }
}
</style>
