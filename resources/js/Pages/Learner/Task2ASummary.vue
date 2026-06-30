<script setup>
import { computed } from 'vue';
import { CheckCircle2, ChevronRight, Sparkles } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';

const props = defineProps({
    attempt: Object,
});

const task1Score = computed(() => props.attempt?.task_1_score ?? 0);
const task2AScore = computed(() => props.attempt?.task_2a_score ?? 0);

const agentMessage = 'Task 2A is now saved. Based on this path, the next reading parts will not be given for now.';
</script>

<template>
    <GuideLayout
        :progress="48"
        diagnostic-step="task-2a"
        layout="stacked"
        max-width="72rem"
        agent-type="evaluator"
        agent-state="encouraging"
        :agent-message="agentMessage"
        agent-line-key="estelle.result.task2a.saved"
        eyebrow="Task 2A Complete"
        divider-label="Saved result"
        primary-label="Continue to CRLA Summary"
        primary-href="/learner/diagnostic/crla-summary"
    >
        <template #primary-icon>
            <ChevronRight class="size-5" />
        </template>

        <template #title>
            Rhyming <span class="guide-title-accent">Word</span> Score
        </template>

        <div class="guide-progress-card guide-anim task2a-summary-panel" style="--guide-delay: 200ms">
            <span class="guide-pill">
                <CheckCircle2 class="size-4" />
                Saved to diagnostic record
            </span>
            <p class="task2a-summary-copy">
                Your CRLA summary will show the completed Task 1 and Task 2A results.
            </p>
        </div>

        <div class="task2a-score-grid">
            <div class="guide-progress-card guide-anim task2a-score-card" style="--guide-delay: 285ms">
                <span class="guide-kicker">Task 1 Letters</span>
                <div class="task2a-score-value">{{ task1Score }}</div>
                <span class="task2a-score-label">Letter pronunciation score</span>
            </div>

            <div class="guide-progress-card guide-anim task2a-score-card task2a-score-card--accent" style="--guide-delay: 330ms">
                <span class="guide-kicker">Task 2A Rhymes</span>
                <div class="task2a-score-value">{{ task2AScore }}</div>
                <span class="task2a-score-label">Rhyming word score</span>
            </div>
        </div>

        <div class="guide-trait guide-anim" style="--guide-delay: 385ms">
            <span class="guide-trait-icon guide-trait-icon--teal"><Sparkles class="size-5" /></span>
            <div class="guide-trait-body">
                <span class="guide-trait-label">What happens next</span>
                <span class="guide-trait-desc">
                    Task 2B and passage reading are not administered when Task 1A is 0-6. The CRLA summary records Task 2B and passage score as 0.
                </span>
            </div>
        </div>
    </GuideLayout>
</template>

<style scoped>
.task2a-summary-panel {
    gap: 0.8rem;
}

.task2a-summary-copy {
    color: var(--rd-text-main);
    font-size: clamp(1.05rem, 2.2vw, 1.45rem);
    font-weight: 900;
    line-height: 1.18;
}

.task2a-score-grid {
    display: grid;
    gap: 0.9rem;
}

@media (min-width: 640px) {
    .task2a-score-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.task2a-score-card {
    position: relative;
    overflow: hidden;
}

.task2a-score-card::after {
    position: absolute;
    right: -1.5rem;
    bottom: -2.4rem;
    width: 6rem;
    height: 6rem;
    border-radius: 50%;
    background: rgba(54, 83, 101, 0.08);
    content: "";
}

.task2a-score-card--accent::after {
    background: rgba(245, 133, 73, 0.12);
}

.task2a-score-value {
    color: var(--rd-text-main);
    font-size: clamp(2.8rem, 6vw, 4rem);
    font-weight: 900;
    line-height: 0.95;
}

.task2a-score-label {
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 800;
    line-height: 1.25;
}
</style>
