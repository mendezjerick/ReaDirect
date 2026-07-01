<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BarChart3,
    BookOpen,
    Check,
    ClipboardCheck,
    GraduationCap,
    Lock,
    MessageSquareText,
    Mic2,
    Route,
    ShieldCheck,
    Star,
    Trophy,
    Type,
} from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    modules: { type: Array, default: () => [] },
    advancedModule: { type: Object, default: () => ({ unlocked: false, completed: false, in_progress: false, route: null, module: null }) },
    latestAttempt: { type: Object, default: null },
    latestFinalAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
    listeningMode: { type: Object, default: () => ({ current: 'manual' }) },
    rewards: { type: Object, default: () => ({ stars: 0 }) },
});

const moduleMeta = {
    module_1: {
        title: 'Letter and Sound Learning',
        shortTitle: 'Letters',
        blurb: 'Practice letter names and sounds.',
        icon: Type,
    },
    module_2: {
        title: 'Word Recognition',
        shortTitle: 'Words',
        blurb: 'Read words quickly and clearly.',
        icon: MessageSquareText,
    },
    module_3: {
        title: 'Sentence Reading and Fluency',
        shortTitle: 'Sentences',
        blurb: 'Read short sentences clearly.',
        icon: BookOpen,
    },
    advanced_module: {
        title: 'Advanced Module',
        shortTitle: 'Advanced',
        blurb: 'Optional fluency practice.',
        icon: Award,
    },
};

const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const currentStage = computed(() => props.flowState?.stage ?? props.learner?.current_stage ?? 'new');
const primaryActionRoute = computed(() => props.flowState?.primary_action_route ?? '/learner/diagnostic/start');
const primaryActionLabel = computed(() => props.flowState?.primary_action_label ?? 'Start Diagnostic');
const primaryMessage = computed(() => props.flowState?.message ?? 'Begin with your diagnostic reading check.');
const totalStars = computed(() => Number(props.rewards?.stars ?? 0));
const specialStars = computed(() => Number(props.rewards?.advanced_stars ?? 0));
const assignedKey = computed(() => props.learner?.current_module?.key ?? props.flowState?.current_module_key ?? null);
const advancedUnlocked = computed(() => props.advancedModule?.unlocked === true && props.advancedModule?.module);
const advancedCompleted = computed(() => props.advancedModule?.completed === true || specialStars.value > 0);
const diagnosticDone = computed(() => props.flowState?.diagnostic?.is_completed === true);
const finalDone = computed(() => (
    props.flowState?.final_reassessment?.is_completed === true
    || ['final_reassessment_completed', 'completed'].includes(currentStage.value)
));
const moduleStageActive = computed(() => [
    'module_assigned',
    'module_practice_in_progress',
    'module_mastery_in_progress',
].includes(currentStage.value));
const finalStageActive = computed(() => [
    'final_reassessment_pending',
    'final_reassessment_in_progress',
].includes(currentStage.value));

const metaFor = (key) => moduleMeta[key] ?? {
    title: 'Learning Module',
    shortTitle: 'Module',
    blurb: 'Practice your assigned reading skill.',
    icon: BookOpen,
};

const numberScore = (value) => Number(value ?? 0);
const readingAccuracy = computed(() => {
    const value = props.latestAttempt?.reading_accuracy;

    if (value == null) return 0;

    const numeric = Number(value);

    return Math.round(numeric <= 1 ? numeric * 100 : numeric);
});
const passageScore = computed(() => Math.round(readingAccuracy.value / 10));
const overallScore = computed(() => {
    if (!diagnosticDone.value) return 0;

    return Math.round((
        numberScore(props.latestAttempt?.task_1_score)
        + numberScore(props.latestAttempt?.task_2b_score ?? props.latestAttempt?.task_2a_score)
        + passageScore.value
    ) / 30 * 100);
});
const overallLabel = computed(() => {
    if (!diagnosticDone.value) return 'Ready to begin';
    if (overallScore.value >= 70) return 'Strong progress';
    if (overallScore.value >= 40) return 'Building steadily';

    return 'Keep practicing';
});

const assignedTitle = computed(() => {
    if (assignedKey.value) return metaFor(assignedKey.value).title;
    if (currentStage.value === 'grade_ready') return 'Grade ready';
    if (currentStage.value.startsWith('final_reassessment')) return 'Final reassessment';
    if (currentStage.value === 'completed') return 'Completed';

    return 'Diagnostic first';
});

const listeningLabel = computed(() => props.listeningMode?.current === 'automatic_ciel' ? 'Automatic' : 'Manual');

const diagnosticState = computed(() => {
    if (diagnosticDone.value) return 'done';
    if (['new', 'diagnostic_in_progress'].includes(currentStage.value)) return 'current';

    return 'done';
});

const moduleStateFor = (moduleKey) => {
    if (moduleKey !== assignedKey.value) return 'locked';
    if (moduleStageActive.value) return 'current';
    if (finalStageActive.value || finalDone.value || currentStage.value === 'completed') return 'done';

    return diagnosticDone.value ? 'current' : 'locked';
};

const finalState = computed(() => {
    if (finalDone.value || currentStage.value === 'completed') return 'done';
    if (finalStageActive.value) return 'current';

    return 'locked';
});

const advancedState = computed(() => {
    if (!advancedUnlocked.value) return 'locked';
    if (advancedCompleted.value) return 'done';

    return 'current';
});

const completionState = computed(() => {
    if (currentStage.value === 'completed') return 'done';
    if (finalDone.value) return 'current';

    return 'locked';
});

const pathNodes = computed(() => {
    const moduleNodes = props.modules
        .filter((module) => module.key !== 'advanced_module')
        .map((module) => {
        const meta = metaFor(module.key);
        const state = moduleStateFor(module.key);

        return {
            id: module.key,
            label: meta.shortTitle,
            detail: meta.blurb,
            icon: meta.icon,
            state,
            href: state === 'current' && module.key === assignedKey.value
                ? `/learner/modules/${module.key}/start`
                : null,
        };
    });
    const advancedNode = advancedUnlocked.value ? [{
        id: 'advanced_module',
        label: metaFor('advanced_module').shortTitle,
        detail: advancedCompleted.value ? 'Special star earned.' : 'Unlocked by a perfect final check.',
        icon: metaFor('advanced_module').icon,
        state: advancedState.value,
        special: true,
        href: advancedState.value === 'current' ? props.advancedModule?.route : null,
    }] : [];

    return [
        {
            id: 'diagnostic',
            label: 'Reading Check',
            detail: diagnosticDone.value ? 'Diagnostic completed.' : 'Start your reading check.',
            icon: ClipboardCheck,
            state: diagnosticState.value,
            href: diagnosticState.value === 'current' ? primaryActionRoute.value : '/learner/progress',
        },
        ...moduleNodes,
        {
            id: 'final',
            label: 'Final Check',
            detail: finalDone.value ? 'Final reassessment completed.' : 'Show what you can do now.',
            icon: GraduationCap,
            state: finalState.value,
            href: finalState.value === 'current' ? primaryActionRoute.value : null,
        },
        ...advancedNode,
        {
            id: 'completion',
            label: 'Completion',
            detail: completionState.value === 'locked' ? 'Unlock this after the final check.' : 'View your certificate.',
            icon: Trophy,
            state: completionState.value,
            href: ['current', 'done'].includes(completionState.value) ? primaryActionRoute.value : null,
        },
    ];
});

const currentPathNode = computed(() => (
    pathNodes.value.find((node) => node.state === 'current')
    ?? [...pathNodes.value].reverse().find((node) => node.state === 'done')
    ?? pathNodes.value[0]
));

const pathProgress = computed(() => {
    const doneCount = pathNodes.value.filter((node) => node.state === 'done').length;
    const currentCredit = pathNodes.value.some((node) => node.state === 'current') ? 0.45 : 0;

    return Math.min(100, Math.round(((doneCount + currentCredit) / pathNodes.value.length) * 100));
});

const statCards = computed(() => [
    {
        label: 'Overall',
        value: `${overallScore.value}%`,
        detail: overallLabel.value,
        icon: BarChart3,
        tone: 'orange',
    },
    {
        label: 'Current Path',
        value: assignedTitle.value,
        detail: primaryActionLabel.value,
        icon: Route,
        tone: 'teal',
    },
    {
        label: 'Stars',
        value: totalStars.value,
        detail: 'Earned in practice',
        icon: Star,
        tone: 'gold',
    },
    {
        label: 'Recording',
        value: listeningLabel.value,
        detail: 'Current mode',
        icon: Mic2,
        tone: 'green',
    },
]);

const scoreCards = computed(() => [
    {
        label: 'Task 1 Letters',
        value: `${numberScore(props.latestAttempt?.task_1_score)}/10`,
        detail: 'Letter names and sounds',
        icon: Type,
    },
    {
        label: 'Task 2A Rhymes',
        value: `${numberScore(props.latestAttempt?.task_2a_score)}/10`,
        detail: 'Rhyme awareness',
        icon: MessageSquareText,
    },
    {
        label: 'Task 2B Words',
        value: `${numberScore(props.latestAttempt?.task_2b_score)}/10`,
        detail: 'Words in sentences',
        icon: BookOpen,
    },
    {
        label: 'Reading',
        value: `${readingAccuracy.value}%`,
        detail: 'Passage accuracy',
        icon: BarChart3,
    },
]);
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Dashboard"
        active="dashboard"
        :show-header="false"
    >
        <section class="dashboard-hero learner-hub-panel">
            <div class="dashboard-hero-copy">
                <span class="learner-hub-badge">
                    <ShieldCheck class="size-4" stroke-width="2.8" />
                    Learner dashboard
                </span>
                <h1 class="dashboard-title">
                    Hi, <span>{{ firstName }}</span>
                </h1>
                <p class="dashboard-subtitle">{{ primaryMessage }}</p>
                <div class="dashboard-actions">
                    <Link :href="primaryActionRoute" class="learner-hub-primary-link">
                        {{ primaryActionLabel }}
                        <ArrowRight class="size-5" stroke-width="3" />
                    </Link>
                    <Link href="/learner/progress" class="learner-hub-secondary-link">
                        View Progress
                    </Link>
                </div>
            </div>

            <div class="dashboard-hero-progress learner-hub-face">
                <div class="dashboard-progress-topline">
                    <span class="dashboard-progress-label">Path progress</span>
                    <span class="dashboard-progress-value">{{ pathProgress }}%</span>
                </div>
                <div class="dashboard-progress-track" aria-hidden="true">
                    <span class="dashboard-progress-fill" :style="{ width: `${pathProgress}%` }" />
                </div>
                <div class="dashboard-current-step">
                    <span class="dashboard-current-star">
                        <Star class="size-5 fill-current" stroke-width="2.8" />
                    </span>
                    <span>
                        <span class="dashboard-current-kicker">Now showing</span>
                        <span class="dashboard-current-title">{{ currentPathNode?.label }}</span>
                    </span>
                </div>
            </div>
        </section>

        <section class="dashboard-stat-grid">
            <article
                v-for="card in statCards"
                :key="card.label"
                class="dashboard-stat learner-hub-card"
                :class="`dashboard-stat--${card.tone}`"
            >
                <span class="dashboard-stat-icon">
                    <component :is="card.icon" class="size-5" stroke-width="2.8" />
                </span>
                <span class="dashboard-stat-body">
                    <span class="dashboard-stat-label">{{ card.label }}</span>
                    <span class="dashboard-stat-value">{{ card.value }}</span>
                    <span class="dashboard-stat-detail">{{ card.detail }}</span>
                </span>
            </article>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-path learner-hub-panel">
                <div class="dashboard-section-head">
                    <div>
                        <p class="learner-hub-kicker">Star path</p>
                        <h2 class="learner-hub-section-title">Your reading route</h2>
                    </div>
                    <span class="dashboard-path-rewards">
                        <span class="dashboard-path-count">{{ totalStars }} stars</span>
                        <span v-if="specialStars > 0" class="dashboard-special-count">
                            <Award class="size-3.5" stroke-width="3" />
                            {{ specialStars }} special
                        </span>
                    </span>
                </div>

                <div class="dashboard-path-list">
                    <div
                        v-for="(node, index) in pathNodes"
                        :key="node.id"
                        class="dashboard-path-row"
                    >
                        <div
                            v-if="index > 0"
                            class="dashboard-path-rail"
                            :class="{ 'dashboard-path-rail--done': pathNodes[index - 1]?.state === 'done' }"
                            aria-hidden="true"
                        />

                        <component
                            :is="node.href ? Link : 'div'"
                            :href="node.href || undefined"
                            class="dashboard-star-button"
                            :class="[
                                `dashboard-star-button--${node.state}`,
                                { 'dashboard-star-button--clickable': Boolean(node.href) },
                                { 'dashboard-star-button--special': node.special && node.state !== 'locked' },
                            ]"
                        >
                            <Award v-if="node.special && node.state !== 'locked'" class="size-6" stroke-width="3.2" />
                            <Check v-else-if="node.state === 'done'" class="size-6" stroke-width="3.4" />
                            <Lock v-else-if="node.state === 'locked'" class="size-5" stroke-width="3" />
                            <Star v-else class="size-7 fill-current" stroke-width="2.8" />
                        </component>

                        <div class="dashboard-path-card learner-hub-face">
                            <span class="dashboard-path-icon">
                                <component :is="node.icon" class="size-4" stroke-width="2.8" />
                            </span>
                            <span class="dashboard-path-copy">
                                <span class="dashboard-path-title">{{ node.label }}</span>
                                <span class="dashboard-path-detail">{{ node.detail }}</span>
                            </span>
                            <span class="dashboard-path-status" :class="`dashboard-path-status--${node.state}`">
                                {{ node.state === 'done' ? 'Done' : node.state === 'current' ? 'Now' : 'Locked' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="dashboard-side">
                <section class="dashboard-next learner-hub-panel">
                    <span class="learner-hub-badge">
                        <Route class="size-4" stroke-width="2.8" />
                        Next step
                    </span>
                    <h2 class="dashboard-next-title">{{ primaryActionLabel }}</h2>
                    <p class="learner-hub-section-copy">{{ primaryMessage }}</p>
                    <Link :href="primaryActionRoute" class="learner-hub-primary-link dashboard-next-action">
                        Continue
                        <ArrowRight class="size-5" stroke-width="3" />
                    </Link>
                </section>

                <section class="dashboard-scores learner-hub-panel">
                    <div class="dashboard-section-head dashboard-section-head--compact">
                        <div>
                            <p class="learner-hub-kicker">Latest scores</p>
                            <h2 class="learner-hub-section-title">Reading check</h2>
                        </div>
                    </div>

                    <div class="dashboard-score-list">
                        <article
                            v-for="score in scoreCards"
                            :key="score.label"
                            class="dashboard-score-card learner-hub-face"
                        >
                            <span class="dashboard-score-icon">
                                <component :is="score.icon" class="size-4" stroke-width="2.8" />
                            </span>
                            <span class="dashboard-score-body">
                                <span class="dashboard-score-label">{{ score.label }}</span>
                                <span class="dashboard-score-detail">{{ score.detail }}</span>
                            </span>
                            <span class="dashboard-score-value">{{ score.value }}</span>
                        </article>
                    </div>
                </section>
            </aside>
        </section>
    </LearnerSimplePageShell>
</template>

<style scoped>
.dashboard-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(17rem, 24rem);
    gap: clamp(1rem, 3vw, 1.5rem);
    align-items: stretch;
}

.dashboard-hero-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
}

.dashboard-title {
    color: var(--rd-text-main);
    font-size: clamp(2.25rem, 6vw, 4.2rem);
    font-weight: 900;
    letter-spacing: 0;
    line-height: 0.98;
}

.dashboard-title span {
    color: var(--rd-primary-orange);
}

.dashboard-subtitle {
    max-width: 38rem;
    color: var(--rd-text-muted);
    font-size: clamp(1rem, 2vw, 1.15rem);
    font-weight: 800;
    line-height: 1.5;
}

.dashboard-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.1rem;
}

.dashboard-hero-progress {
    display: grid;
    align-content: center;
    gap: 1rem;
    padding: 1rem;
}

.dashboard-progress-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.dashboard-progress-label,
.dashboard-current-kicker {
    color: var(--rd-text-muted);
    font-size: 0.7rem;
    font-weight: 900;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.dashboard-progress-value {
    color: var(--rd-primary-orange);
    font-size: 1.5rem;
    font-weight: 900;
    line-height: 1;
}

.dashboard-progress-track {
    height: 0.95rem;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(54, 83, 101, 0.1);
    box-shadow: inset 0 1px 2px rgba(54, 83, 101, 0.16);
}

.dashboard-progress-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--rd-primary-orange), var(--rd-secondary-orange));
    box-shadow: 0 2px 8px rgba(245, 133, 73, 0.25);
}

.dashboard-current-step {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    border: 1.5px solid rgba(245, 133, 73, 0.2);
    border-radius: 1rem;
    background: rgba(245, 133, 73, 0.08);
    padding: 0.8rem;
}

.dashboard-current-star {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    place-items: center;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 5px 0 #b84b24, 0 10px 16px rgba(245, 133, 73, 0.24);
}

.dashboard-current-title {
    display: block;
    margin-top: 0.15rem;
    color: var(--rd-text-main);
    font-size: 1rem;
    font-weight: 900;
}

.dashboard-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.9rem;
    margin-top: 1.1rem;
}

.dashboard-stat {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.8rem;
}

.dashboard-stat-icon {
    display: grid;
    width: 2.7rem;
    height: 2.7rem;
    flex-shrink: 0;
    place-items: center;
    border: 1.5px solid rgba(245, 133, 73, 0.22);
    border-radius: 0.85rem;
    background: rgba(245, 133, 73, 0.1);
    color: var(--rd-primary-orange);
}

.dashboard-stat--teal .dashboard-stat-icon {
    border-color: rgba(54, 83, 101, 0.22);
    background: rgba(54, 83, 101, 0.09);
    color: var(--rd-depth-blue);
}

.dashboard-stat--gold .dashboard-stat-icon {
    border-color: rgba(238, 193, 112, 0.42);
    background: rgba(238, 193, 112, 0.16);
    color: #b45309;
}

.dashboard-stat--green .dashboard-stat-icon {
    border-color: rgba(16, 185, 129, 0.26);
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.dashboard-stat-body {
    display: grid;
    min-width: 0;
    gap: 0.08rem;
}

.dashboard-stat-label {
    color: var(--rd-text-muted);
    font-size: 0.68rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.dashboard-stat-value {
    overflow: hidden;
    color: var(--rd-text-main);
    font-size: 1.05rem;
    font-weight: 900;
    line-height: 1.12;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-stat-detail {
    color: var(--rd-text-muted);
    font-size: 0.74rem;
    font-weight: 800;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 24rem);
    gap: 1.1rem;
    margin-top: 1.1rem;
    align-items: start;
}

.dashboard-section-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.dashboard-section-head--compact {
    margin-bottom: 0.8rem;
}

.dashboard-path-rewards {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.45rem;
}

.dashboard-path-count,
.dashboard-special-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(238, 193, 112, 0.22);
    padding: 0.45rem 0.8rem;
    color: #92400e;
    font-size: 0.78rem;
    font-weight: 900;
    white-space: nowrap;
}

.dashboard-special-count {
    gap: 0.28rem;
    background: rgba(30, 156, 150, 0.14);
    color: #0f766e;
}

.dashboard-path-list {
    display: grid;
    gap: 0.2rem;
}

.dashboard-path-row {
    position: relative;
    display: grid;
    grid-template-columns: 3.7rem minmax(0, 1fr);
    gap: 0.85rem;
    align-items: center;
    min-height: 5.2rem;
}

.dashboard-path-rail {
    position: absolute;
    top: -1.55rem;
    left: 1.78rem;
    width: 0.28rem;
    height: 2.6rem;
    border-radius: 999px;
    background: rgba(54, 83, 101, 0.12);
}

.dashboard-path-rail--done {
    background: linear-gradient(180deg, var(--rd-secondary-orange), var(--rd-primary-orange));
}

.dashboard-star-button {
    position: relative;
    z-index: 1;
    display: grid;
    width: 3.7rem;
    height: 3.7rem;
    place-items: center;
    border: 2px solid rgba(255, 255, 255, 0.48);
    border-radius: 999px;
    color: #fff;
    text-decoration: none;
    box-shadow: 0 6px 0 rgba(111, 101, 52, 0.24), 0 12px 18px rgba(54, 83, 101, 0.16);
}

.dashboard-star-button--current {
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    box-shadow: 0 7px 0 #b84b24, 0 14px 24px rgba(245, 133, 73, 0.25);
}

.dashboard-star-button--done {
    background: linear-gradient(180deg, #9bb164, var(--rd-correct-green));
    box-shadow: 0 7px 0 var(--rd-correct-green-dark), 0 14px 22px rgba(88, 81, 35, 0.2);
}

.dashboard-star-button--locked {
    background: linear-gradient(180deg, #d6d9dc, #aeb6bd);
    color: #fff;
    box-shadow: 0 5px 0 #87929b, 0 10px 16px rgba(54, 83, 101, 0.12);
}

.dashboard-star-button--special {
    background: linear-gradient(180deg, #f8d783, #1e9c96);
    color: #fffdf2;
    box-shadow: 0 7px 0 #0f766e, 0 14px 24px rgba(30, 156, 150, 0.2);
}

.dashboard-star-button--clickable:hover {
    transform: translateY(-3px);
}

.dashboard-path-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.8rem;
    min-height: 4.3rem;
    padding: 0.8rem 0.9rem;
}

.dashboard-path-icon {
    display: grid;
    width: 2.1rem;
    height: 2.1rem;
    place-items: center;
    border-radius: 0.7rem;
    background: rgba(54, 83, 101, 0.08);
    color: var(--rd-depth-blue);
}

.dashboard-path-copy {
    display: grid;
    min-width: 0;
    gap: 0.1rem;
}

.dashboard-path-title {
    color: var(--rd-text-main);
    font-size: 0.96rem;
    font-weight: 900;
    line-height: 1.15;
}

.dashboard-path-detail {
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.3;
}

.dashboard-path-status {
    border-radius: 999px;
    padding: 0.34rem 0.6rem;
    font-size: 0.68rem;
    font-weight: 900;
    line-height: 1;
}

.dashboard-path-status--done {
    background: rgba(88, 81, 35, 0.12);
    color: var(--rd-correct-green);
}

.dashboard-path-status--current {
    background: rgba(245, 133, 73, 0.12);
    color: var(--rd-primary-orange);
}

.dashboard-path-status--locked {
    background: rgba(54, 83, 101, 0.08);
    color: var(--rd-text-muted);
}

.dashboard-side {
    display: grid;
    gap: 1.1rem;
}

.dashboard-next {
    display: grid;
    gap: 0.9rem;
}

.dashboard-next-title {
    color: var(--rd-text-main);
    font-size: clamp(1.35rem, 2.8vw, 1.85rem);
    font-weight: 900;
    line-height: 1.1;
}

.dashboard-next-action {
    width: 100%;
}

.dashboard-score-list {
    display: grid;
    gap: 0.6rem;
}

.dashboard-score-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.75rem;
    padding: 0.72rem;
}

.dashboard-score-icon {
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border-radius: 0.65rem;
    background: rgba(245, 133, 73, 0.1);
    color: var(--rd-primary-orange);
}

.dashboard-score-body {
    display: grid;
    min-width: 0;
}

.dashboard-score-label,
.dashboard-score-value {
    color: var(--rd-text-main);
    font-size: 0.84rem;
    font-weight: 900;
}

.dashboard-score-detail {
    overflow: hidden;
    color: var(--rd-text-muted);
    font-size: 0.7rem;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dashboard-score-value {
    color: var(--rd-primary-orange);
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .dashboard-hero,
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .dashboard-actions,
    .dashboard-actions > *,
    .dashboard-next-action {
        width: 100%;
    }

    .dashboard-stat-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-path-row {
        grid-template-columns: 3.2rem minmax(0, 1fr);
        gap: 0.65rem;
    }

    .dashboard-star-button {
        width: 3.2rem;
        height: 3.2rem;
    }

    .dashboard-path-rail {
        left: 1.52rem;
    }

    .dashboard-path-card {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .dashboard-path-icon {
        display: none;
    }
}
</style>
