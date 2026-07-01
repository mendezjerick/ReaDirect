<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Award, BookOpen, CheckCircle2, Lock, ShieldCheck, Star, Trophy } from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    latestAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
    rewards: { type: Object, default: () => ({ stars: 0 }) },
});

const diagnosticDone = computed(() => props.flowState?.diagnostic?.is_completed === true);
const moduleStarted = computed(() => Boolean(props.flowState?.module?.current_module_key ?? props.flowState?.current_module_key));
const readingDone = computed(() => Number(props.latestAttempt?.reading_accuracy ?? 0) > 0);
const totalStars = computed(() => Number(props.rewards?.stars ?? 0));
const specialStars = computed(() => Number(props.rewards?.advanced_stars ?? 0));

const rewardItems = computed(() => [
    { title: 'Diagnostic Explorer', detail: 'Complete the first reading check.', unlocked: diagnosticDone.value, icon: Trophy },
    { title: 'Module Starter', detail: 'Open your assigned learning module.', unlocked: moduleStarted.value, icon: BookOpen },
    { title: 'Passage Reader', detail: 'Finish a passage reading check.', unlocked: readingDone.value, icon: Star },
    { title: 'Path Builder', detail: 'Keep practicing until the next checkpoint.', unlocked: totalStars.value > 0, icon: Award },
    { title: 'Advanced Star', detail: 'Complete the optional Advanced Module.', unlocked: specialStars.value > 0, icon: Award, special: true },
]);
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Rewards"
        subtitle="Milestones from your reading journey"
        active="rewards"
    >
        <section class="rewards-hero learner-hub-panel">
            <div>
                <span class="learner-hub-badge">
                    <ShieldCheck class="size-4" stroke-width="2.8" />
                    Reward path
                </span>
                <h2 class="rewards-hero-title">Stars and badges</h2>
                <p class="learner-hub-section-copy">
                    Stars come from practice. Badges show the larger milestones you have unlocked.
                </p>
            </div>

            <div class="rewards-star-total learner-hub-face">
                <Star class="size-10 fill-current" stroke-width="2.8" />
                <span>
                    <span class="rewards-star-count">{{ totalStars }}</span>
                    <span class="rewards-star-label">stars earned</span>
                </span>
            </div>

            <div v-if="specialStars > 0" class="rewards-star-total rewards-star-total--special learner-hub-face">
                <Award class="size-10" stroke-width="2.8" />
                <span>
                    <span class="rewards-star-count">{{ specialStars }}</span>
                    <span class="rewards-star-label">special star</span>
                </span>
            </div>
        </section>

        <section class="rewards-grid">
            <article
                v-for="reward in rewardItems"
                :key="reward.title"
                class="rewards-card learner-hub-card"
                :class="{ 'rewards-card--locked': !reward.unlocked }"
            >
                <span
                    class="rewards-card-icon"
                    :class="{
                        'rewards-card-icon--locked': !reward.unlocked,
                        'rewards-card-icon--special': reward.special && reward.unlocked,
                    }"
                >
                    <component :is="reward.unlocked ? reward.icon : Lock" class="size-6" stroke-width="2.8" />
                </span>
                <span class="rewards-card-body">
                    <span class="rewards-card-status" :class="{ 'rewards-card-status--locked': !reward.unlocked }">
                        <CheckCircle2 v-if="reward.unlocked" class="size-3.5" stroke-width="3" />
                        {{ reward.unlocked ? 'Unlocked' : 'Locked' }}
                    </span>
                    <span class="rewards-card-title">{{ reward.title }}</span>
                    <span class="rewards-card-detail">{{ reward.detail }}</span>
                </span>
            </article>
        </section>

        <section class="rewards-next learner-hub-panel">
            <div>
                <p class="learner-hub-kicker">Next step</p>
                <h2 class="learner-hub-section-title">{{ flowState?.primary_action_label ?? 'Continue' }}</h2>
                <p class="learner-hub-section-copy">
                    {{ flowState?.message ?? 'Continue your reading path from the dashboard.' }}
                </p>
            </div>
            <Link
                :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                class="learner-hub-primary-link rewards-action"
            >
                {{ flowState?.primary_action_label ?? 'Continue' }}
                <ArrowRight class="size-5" stroke-width="3" />
            </Link>
        </section>
    </LearnerSimplePageShell>
</template>

<style scoped>
.rewards-hero {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 1rem;
}

.rewards-hero-title {
    margin-top: 0.7rem;
    color: var(--rd-text-main);
    font-size: clamp(1.7rem, 4vw, 2.6rem);
    font-weight: 900;
    line-height: 1.05;
}

.rewards-star-total {
    display: flex;
    min-width: 14rem;
    align-items: center;
    justify-content: center;
    gap: 0.85rem;
    padding: 1rem;
    color: #b45309;
}

.rewards-star-total--special {
    color: #0f766e;
}

.rewards-star-count {
    display: block;
    color: var(--rd-text-main);
    font-size: clamp(2.4rem, 6vw, 4rem);
    font-weight: 900;
    line-height: 0.9;
}

.rewards-star-label {
    display: block;
    margin-top: 0.2rem;
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.rewards-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.9rem;
    margin-top: 1.1rem;
}

.rewards-card {
    display: grid;
    align-content: start;
    gap: 1rem;
    min-height: 14rem;
}

.rewards-card--locked {
    opacity: 0.76;
}

.rewards-card-icon {
    display: grid;
    width: 3.5rem;
    height: 3.5rem;
    place-items: center;
    border-radius: 1rem;
    background: linear-gradient(180deg, var(--rd-action-button-light), var(--rd-action-button));
    color: #fff;
    box-shadow: 0 5px 0 #b84b24, 0 10px 16px rgba(245, 133, 73, 0.2);
}

.rewards-card-icon--locked {
    background: linear-gradient(180deg, #d6d9dc, #aeb6bd);
    box-shadow: 0 5px 0 #87929b, 0 10px 16px rgba(54, 83, 101, 0.12);
}

.rewards-card-icon--special {
    background: linear-gradient(180deg, #f8d783, #1e9c96);
    box-shadow: 0 5px 0 #0f766e, 0 10px 16px rgba(30, 156, 150, 0.18);
}

.rewards-card-body {
    display: grid;
    gap: 0.45rem;
}

.rewards-card-status {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    background: rgba(88, 81, 35, 0.12);
    padding: 0.32rem 0.6rem;
    color: var(--rd-correct-green);
    font-size: 0.7rem;
    font-weight: 900;
}

.rewards-card-status--locked {
    background: rgba(54, 83, 101, 0.08);
    color: var(--rd-text-muted);
}

.rewards-card-title {
    color: var(--rd-text-main);
    font-size: 1.08rem;
    font-weight: 900;
    line-height: 1.12;
}

.rewards-card-detail {
    color: var(--rd-text-muted);
    font-size: 0.84rem;
    font-weight: 800;
    line-height: 1.4;
}

.rewards-next {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1.1rem;
}

.rewards-action {
    flex-shrink: 0;
}

@media (max-width: 1024px) {
    .rewards-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .rewards-hero,
    .rewards-next {
        flex-direction: column;
        align-items: stretch;
    }

    .rewards-action {
        width: 100%;
    }
}

@media (max-width: 560px) {
    .rewards-grid {
        grid-template-columns: 1fr;
    }
}
</style>
