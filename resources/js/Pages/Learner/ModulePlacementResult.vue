<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import LessonCard from '../../Components/LessonCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ decision: Object, module: Object });
</script>

<template>
    <LearnerLayout :progress="100">
        <template #agent>
            <AgentSpeakerPanel agent-type="evaluator" state="celebrating" message="Your reading path is ready. This recommendation follows the ReaDirect rules." />
        </template>
        <section class="mx-auto grid max-w-2xl gap-6 text-center">
            <RewardBadge title="Path Ready" />
            <h1 class="text-4xl font-black text-text">Your reading path is ready.</h1>
            <LessonCard :title="module?.title ?? 'Reading at Grade Level'" :description="decision.decision_reason" active />
            <p class="text-base font-bold text-muted">Rule applied: {{ decision.rule_applied }}</p>
        </section>
        <BottomActionBar>
            <Link v-if="module" :href="`/learner/modules/${module.key}/start`"><PrimaryButton>Start module</PrimaryButton></Link>
            <Link v-else href="/learner/dashboard"><PrimaryButton>Back home</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
