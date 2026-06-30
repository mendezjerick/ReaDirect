<script setup>
import { computed } from 'vue';
import { ArrowRight, BookOpen, CheckCircle2, ClipboardCheck, Pencil, Play } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';

const props = defineProps({ module: Object, learnerStage: String, flowState: Object });

const steps = [
    { key: 'start', label: 'Start', description: 'Begin the module', icon: Play, tone: 'green' },
    { key: 'practice', label: 'Practice', description: 'Read and record', icon: Pencil, tone: 'teal' },
    { key: 'check', label: 'Check', description: 'Review your progress', icon: ClipboardCheck, tone: 'violet' },
    { key: 'next', label: 'Next', description: 'Move forward', icon: CheckCircle2, tone: 'green' },
];

const actionHref = computed(() => props.module?.key
    ? `/learner/modules/${props.module.key}/start`
    : props.flowState?.primary_action_route ?? '/learner/dashboard');

const actionLabel = computed(() => props.module ? 'Continue' : props.flowState?.primary_action_label ?? 'Back Home');
const agentMessage = "Take your time, then read this one out loud. I'll stay with you, and we can go slowly together.";
</script>

<template>
    <GuideLayout
        :progress="72"
        back-url="/learner/dashboard"
        back-label="Back to Learner Dashboard"
        agent-type="coach_feedback"
        agent-state="speaking"
        :agent-message="agentMessage"
        agent-line-key="ciel.friendly.read_slowly_together"
        eyebrow="Learning Module"
        divider-label="Module path"
        :primary-label="actionLabel"
        :primary-href="actionHref"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Learning <span class="guide-title-accent">Module</span>
        </template>

        <section class="module-index-shell">
            <div v-if="module" class="guide-progress-card guide-anim module-index-current" style="--guide-delay: 200ms">
                <span class="guide-pill">
                    <BookOpen class="size-4" />
                    Current module
                </span>
                <h2 class="module-index-title">{{ module.title }}</h2>
                <p class="module-index-description">{{ module.description }}</p>
            </div>

            <div v-else class="guide-status guide-status--warning guide-anim" style="--guide-delay: 200ms">
                {{ flowState?.message ?? 'Your diagnostic result shows grade-level readiness.' }}
            </div>

            <div class="guide-traits">
                <div
                    v-for="(step, index) in steps"
                    :key="step.key"
                    class="guide-trait guide-anim"
                    :style="`--guide-delay: ${285 + index * 45}ms`"
                >
                    <span
                        class="guide-trait-icon"
                        :class="{
                            'guide-trait-icon--green': step.tone === 'green',
                            'guide-trait-icon--teal': step.tone === 'teal',
                            'guide-trait-icon--violet': step.tone === 'violet',
                        }"
                    >
                        <component :is="step.icon" class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="guide-trait-body">
                        <span class="guide-trait-label">{{ step.label }}</span>
                        <span class="guide-trait-desc">{{ step.description }}</span>
                    </span>
                </div>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.module-index-shell {
    display: grid;
    gap: 1rem;
}

.module-index-current {
    align-items: start;
    padding: clamp(1.1rem, 3vw, 1.55rem);
}

.module-index-title {
    color: var(--rd-text-main);
    font-size: clamp(1.35rem, 3vw, 2rem);
    font-weight: 900;
    line-height: 1.08;
}

.module-index-description {
    color: var(--rd-text-muted);
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.38;
}
</style>
