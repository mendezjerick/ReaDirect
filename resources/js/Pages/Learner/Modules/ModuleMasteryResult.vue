<script setup>
import { computed } from 'vue';
import { ArrowRight, CheckCircle2, RotateCcw, Star, Trophy } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';

const props = defineProps({ module: Object, score: Number, decision: Object, resultMessage: String, nextModule: Object });

const actionLabel = (decisionKey) => ({
    move_to_module_2: 'Continue to Module 2',
    move_to_module_3: 'Continue to Module 3',
    repeat_module_1: 'Practice Module 1 Again',
    repeat_module_2: 'Practice Module 2 Again',
    repeat_module_3: 'Practice Module 3 Again',
    return_to_module_1: 'Return to Module 1',
    return_to_module_2: 'Return to Module 2',
    proceed_to_reassessment: 'Go to Dashboard',
}[decisionKey] ?? 'Continue');

const actionHref = (decision, module, nextModule) => {
    if (decision?.decision_key === 'proceed_to_reassessment') return '/learner/dashboard';

    const moduleKey = nextModule?.key ?? module?.key;
    return moduleKey ? `/learner/modules/${moduleKey}/start` : '/learner/dashboard';
};

const masteryVoiceLine = 'Your mastery result is ready. This helps us see what you learned and what you can practice next.';
const resolvedActionLabel = computed(() => actionLabel(props.decision?.decision_key));
const resolvedActionHref = computed(() => actionHref(props.decision, props.module, props.nextModule));
const resolvedResultMessage = computed(() => props.resultMessage ?? props.decision?.user_friendly_message ?? 'Your module check result is ready.');
const currentModuleTitle = computed(() => props.module?.title ?? props.module?.name ?? 'Module Check');
const isRepeatAction = computed(() => String(props.decision?.decision_key ?? '').includes('repeat') || String(props.decision?.decision_key ?? '').includes('return'));
</script>

<template>
    <GuideLayout
        :progress="100"
        layout="stacked"
        max-width="60rem"
        agent-type="evaluator"
        agent-state="celebrating"
        :agent-message="masteryVoiceLine"
        agent-line-key="estelle.result.mastery_ready"
        eyebrow="Module Check Complete"
        divider-label="Mastery result"
        :primary-label="resolvedActionLabel"
        :primary-href="resolvedActionHref"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Great <span class="guide-title-accent">Effort</span>
        </template>

        <section class="mastery-result-shell">
            <div class="guide-progress-card guide-anim mastery-score-card" style="--guide-delay: 200ms">
                <span class="guide-pill">
                    <Trophy class="size-4" />
                    {{ currentModuleTitle }}
                </span>
                <div class="mastery-score-value">{{ score ?? '-' }}<span>%</span></div>
                <p class="mastery-score-label">Mastery score</p>
            </div>

            <div class="guide-trait guide-anim mastery-message-card" style="--guide-delay: 285ms">
                <span class="guide-trait-icon" :class="isRepeatAction ? 'guide-trait-icon--violet' : 'guide-trait-icon--green'">
                    <RotateCcw v-if="isRepeatAction" class="size-5" />
                    <CheckCircle2 v-else class="size-5" />
                </span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">What this means</span>
                    <span class="mastery-message-text">{{ resolvedResultMessage }}</span>
                </div>
            </div>

            <div class="guide-progress-card guide-anim mastery-next-card" style="--guide-delay: 360ms">
                <span class="guide-pill guide-pill--muted">
                    <Star class="size-4" />
                    Next step
                </span>
                <p class="mastery-next-copy">
                    Miss Estelle will send you to the next activity that fits this result.
                </p>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.mastery-result-shell {
    display: grid;
    gap: 1rem;
    width: 100%;
}

.mastery-score-card {
    justify-items: center;
    padding: clamp(1.25rem, 3vw, 1.8rem);
    text-align: center;
}

.mastery-score-value {
    color: var(--rd-text-main);
    font-size: clamp(3.4rem, 9vw, 5.5rem);
    font-weight: 900;
    line-height: 0.95;
}

.mastery-score-value span {
    color: rgba(54, 83, 101, 0.25);
    font-size: 0.46em;
}

.mastery-score-label {
    color: var(--rd-text-muted);
    font-size: 0.74rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    line-height: 1;
    text-transform: uppercase;
}

.mastery-message-card {
    align-items: flex-start;
    text-align: left;
}

.mastery-message-text {
    color: var(--rd-text-main);
    font-size: clamp(1.05rem, 2.2vw, 1.35rem);
    font-weight: 900;
    line-height: 1.18;
}

.mastery-next-card {
    justify-items: center;
    text-align: center;
}

.mastery-next-copy {
    color: var(--rd-text-muted);
    font-size: 0.92rem;
    font-weight: 800;
    line-height: 1.35;
}
</style>
