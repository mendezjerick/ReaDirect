<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import LessonCard from '../../Components/LessonCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({ decision: Object, module: Object });

const moduleTitle = computed(() => props.module?.title ?? 'Reading at Grade Level');

const evaluatorMessage = computed(() => {
    if (props.module) {
        return `Great job! Your reading path is ${moduleTitle.value}. Tap continue to see it on your dashboard.`;
    }
    return "Wonderful! You're reading at grade level. Tap continue to head to your dashboard.";
});
</script>

<template>
    <LearnerLayout :progress="100">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="celebrating"
                :message="evaluatorMessage"
            />
        </template>

        <section class="mx-auto grid max-w-2xl gap-6 text-center">
            <RewardBadge title="Path Ready" />
            <h1 class="text-4xl font-black text-text">Your reading path is ready.</h1>
            <LessonCard :title="moduleTitle" :description="decision.decision_reason" active />
            <p class="text-base font-bold text-muted">Rule applied: {{ decision.rule_applied }}</p>
        </section>

        <BottomActionBar>
            <Link href="/learner/dashboard">
                <PrimaryButton>Continue to my Dashboard</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
