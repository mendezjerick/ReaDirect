<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ attempt: Object, route: Object });
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel agent-type="evaluator" state="pointing" message="I checked the letter score and found the next reading step." />
        </template>
        <div class="mx-auto grid max-w-2xl gap-6">
            <PromptCard label="Good effort" :prompt="route?.requires_task_2a ? 'Next: rhyming' : 'Next: words'" size="word" />
            <AgentPanel title="Assessment Agent">{{ attempt?.decision_reason }}</AgentPanel>
        </div>
        <BottomActionBar>
            <Link :href="route?.requires_task_2a ? '/learner/diagnostic/task-2a' : '/learner/diagnostic/task-2b'">
                <PrimaryButton>Continue</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
