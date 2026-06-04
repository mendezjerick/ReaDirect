<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import ScoreCard from '../../../Components/ScoreCard.vue';
import RewardBadge from '../../../Components/RewardBadge.vue';

defineProps({ module: Object, score: Number, decision: Object, resultMessage: String, nextModule: Object });

const actionLabel = (decisionKey) => ({
    move_to_module_2: 'Continue to Module 2',
    move_to_module_3: 'Continue to Module 3',
    repeat_module_1: 'Practice Module 1 again',
    repeat_module_2: 'Practice Module 2 again',
    repeat_module_3: 'Practice Module 3 again',
    return_to_module_1: 'Return to Module 1',
    return_to_module_2: 'Return to Module 2',
    proceed_to_reassessment: 'Go to dashboard',
}[decisionKey] ?? 'Continue');

const actionHref = (decision, module, nextModule) => {
    if (decision.decision_key === 'proceed_to_reassessment') return '/learner/dashboard';
    return `/learner/modules/${nextModule?.key ?? module.key}/start`;
};
</script>

<template>
    <LearnerLayout :progress="100">
        <template #agent>
            <AgentSpeakerPanel agent-type="evaluator" state="celebrating" :message="resultMessage ?? decision.user_friendly_message" />
        </template>

        <section class="mx-auto grid max-w-2xl gap-5 text-center">
            <RewardBadge title="Module Check Complete" />
            <h1 class="text-4xl font-black text-text">Great effort!</h1>
            <ScoreCard label="Mastery score" :value="`${score}%`" />
            <div class="rounded-[28px] border border-border bg-surface p-6 shadow-lg shadow-primary/10">
                <p class="text-xl font-black text-text">{{ resultMessage ?? decision.user_friendly_message }}</p>
            </div>
        </section>

        <BottomActionBar>
            <Link :href="actionHref(decision, module, nextModule)">
                <PrimaryButton>{{ actionLabel(decision.decision_key) }}</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
