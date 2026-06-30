<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, HelpCircle, Mic, RotateCcw, ShieldCheck, Volume2 } from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

defineProps({
    learner: { type: Object, default: null },
    latestAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
});

const helpCards = [
    {
        title: 'Recording your voice',
        detail: 'Wait for the cue, speak clearly, then listen before submitting.',
        icon: Mic,
    },
    {
        title: 'Hearing the guide',
        detail: 'Use the speaker or replay button when a guide message does not play automatically.',
        icon: Volume2,
    },
    {
        title: 'Trying again',
        detail: 'If the recording is unclear, use Try Again or Record Again before moving on.',
        icon: RotateCcw,
    },
    {
        title: 'Finding your lesson',
        detail: 'Open My Learning when you want to return to your current module.',
        icon: BookOpen,
    },
];
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Help"
        subtitle="Quick help for reading activities"
        active="help"
    >
        <section class="help-layout">
            <div class="help-main learner-hub-panel">
                <div class="help-intro learner-hub-face">
                    <span class="learner-hub-badge">
                        <HelpCircle class="size-4" stroke-width="2.8" />
                        Need help
                    </span>
                    <h2 class="help-title">Common things to check</h2>
                    <p class="learner-hub-section-copy">
                        These are the usual places to look when an activity feels stuck or unclear.
                    </p>
                </div>

                <div class="help-card-grid">
                    <article
                        v-for="card in helpCards"
                        :key="card.title"
                        class="help-card learner-hub-card"
                    >
                        <span class="help-card-icon">
                            <component :is="card.icon" class="size-5" stroke-width="2.8" />
                        </span>
                        <span class="help-card-body">
                            <span class="help-card-title">{{ card.title }}</span>
                            <span class="help-card-detail">{{ card.detail }}</span>
                        </span>
                    </article>
                </div>
            </div>

            <aside class="help-side learner-hub-panel">
                <span class="learner-hub-badge">
                    <ShieldCheck class="size-4" stroke-width="2.8" />
                    Next step
                </span>
                <h2 class="help-side-title">{{ flowState?.primary_action_label ?? 'Continue' }}</h2>
                <p class="learner-hub-section-copy">
                    {{ flowState?.message ?? 'Return to the dashboard and continue your reading path.' }}
                </p>
                <Link
                    :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                    class="learner-hub-primary-link help-action"
                >
                    {{ flowState?.primary_action_label ?? 'Continue' }}
                    <ArrowRight class="size-5" stroke-width="3" />
                </Link>
            </aside>
        </section>
    </LearnerSimplePageShell>
</template>

<style scoped>
.help-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 23rem);
    gap: 1.1rem;
    align-items: start;
}

.help-main {
    display: grid;
    gap: 1rem;
}

.help-intro {
    display: grid;
    gap: 0.65rem;
    padding: 1.1rem;
}

.help-title {
    color: var(--rd-text-main);
    font-size: clamp(1.6rem, 4vw, 2.45rem);
    font-weight: 900;
    line-height: 1.05;
}

.help-card-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.help-card {
    display: flex;
    min-width: 0;
    align-items: flex-start;
    gap: 0.85rem;
}

.help-card-icon {
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

.help-card-body {
    display: grid;
    min-width: 0;
    gap: 0.3rem;
}

.help-card-title {
    color: var(--rd-text-main);
    font-size: 1rem;
    font-weight: 900;
    line-height: 1.15;
}

.help-card-detail {
    color: var(--rd-text-muted);
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1.4;
}

.help-side {
    display: grid;
    gap: 0.9rem;
}

.help-side-title {
    color: var(--rd-text-main);
    font-size: clamp(1.35rem, 3vw, 1.85rem);
    font-weight: 900;
    line-height: 1.1;
}

.help-action {
    width: 100%;
}

@media (max-width: 960px) {
    .help-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .help-card-grid {
        grid-template-columns: 1fr;
    }
}
</style>
