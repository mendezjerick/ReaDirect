<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({ attempt: Object, route: Object });

const requiresTask2A = props.route?.requires_task_2a ?? ((props.attempt?.task_1_score ?? 0) <= 6);
const nextHref = requiresTask2A ? '/learner/diagnostic/task-2a' : '/learner/diagnostic/task-2b';
const nextTitle = requiresTask2A ? 'Task 2A: Rhyming Words' : 'Task 2B: Word in Sentence';
const nextSummary = requiresTask2A
    ? 'You will continue to the rhyming task next.'
    : 'Task 2A is skipped, and you will continue directly to the sentence-reading task.';
const taskTwoAScoreLabel = requiresTask2A ? 'Required next' : `${props.route?.assigned_task_2a_score ?? props.attempt?.task_2a_score ?? 10}/10`;
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="pointing"
                :message="requiresTask2A ? 'I checked the letter score. Task 2A is required before we move on.' : 'I checked the letter score. Task 2A is skipped, so we can move straight to the next reading task.'"
            />
        </template>
        <div class="mx-auto grid max-w-2xl gap-6">
            <h1 class="text-center text-4xl font-black text-text">Task 1 routing complete.</h1>
            <div class="grid gap-4 md:grid-cols-2">
                <ScoreCard label="Task 1 score" :value="`${attempt?.task_1_score ?? 0}/10`" />
                <ScoreCard label="Task 2A status" :value="taskTwoAScoreLabel" />
            </div>
            <AgentPanel title="Next task">{{ nextTitle }}</AgentPanel>
            <AgentPanel title="Routing reason">{{ attempt?.decision_reason }}</AgentPanel>
            <AgentPanel title="What happens now">{{ nextSummary }}</AgentPanel>
        </div>
        <BottomActionBar>
            <Link :href="nextHref">
                <PrimaryButton>Continue</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
