<script setup>
import { ref } from 'vue';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import ScoreCard from '../../../Components/ScoreCard.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

defineProps({ attempt: Object, comparison: Object });

const agentAction = ref('results');

const handleAgentInteractionEnded = ({ action }) => {
    if (action === 'results') {
        agentAction.value = 'congrats';
    }
};

const deltaLabel = (value) => {
    if (value === null || value === undefined) return '-';
    return value > 0 ? `+${value}` : String(value);
};
</script>

<template>
    <LearnerLayout :progress="100" backUrl="/learner/dashboard" backLabel="Back to dashboard">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                :state="agentAction"
                allow-congrats
                message="Great job finishing your final assessment. Here is how your reading changed."
                @interaction-ended="handleAgentInteractionEnded"
            />
        </template>

        <section class="mx-auto grid max-w-4xl gap-5">
            <h1 class="text-center text-4xl font-black text-text">Final check complete.</h1>
            <div class="grid gap-4 lg:grid-cols-4">
                <ScoreCard label="Final CRLA" :value="attempt.crla_total_score ?? '-'" />
                <ScoreCard label="CRLA Growth" :value="deltaLabel(comparison.deltas?.crla_total_score)" />
                <ScoreCard label="Final Reading" :value="attempt.final_reading_score ?? '-'" />
                <ScoreCard label="Reading Growth" :value="deltaLabel(comparison.deltas?.final_reading_score)" />
            </div>

            <DashboardCard>
                <h2 class="text-xl font-black text-text">Your progress</h2>
                <p class="mt-2 text-muted">{{ comparison.summary }}</p>
                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                    <div class="rounded-2xl bg-background p-4">
                        <p class="text-sm font-black text-muted">Task 1 Change</p>
                        <p class="text-3xl font-black text-primary">{{ deltaLabel(comparison.deltas?.task_1_score) }}</p>
                    </div>
                    <div class="rounded-2xl bg-background p-4">
                        <p class="text-sm font-black text-muted">Task 2B Change</p>
                        <p class="text-3xl font-black text-primary">{{ deltaLabel(comparison.deltas?.task_2b_score) }}</p>
                    </div>
                    <div class="rounded-2xl bg-background p-4">
                        <p class="text-sm font-black text-muted">Accuracy Change</p>
                        <p class="text-3xl font-black text-primary">{{ deltaLabel(comparison.deltas?.reading_accuracy) }}%</p>
                    </div>
                </div>
            </DashboardCard>
        </section>

    </LearnerLayout>
</template>
