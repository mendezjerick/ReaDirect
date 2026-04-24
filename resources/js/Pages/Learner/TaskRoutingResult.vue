<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ attempt: Object, route: Object });
</script>

<template>
    <LearnerLayout :progress="72">
        <div class="mx-auto grid max-w-2xl gap-6">
            <PromptCard label="Good effort" :prompt="route?.requires_task_2a ? 'Next: rhyming' : 'Next: words'" size="word" />
            <AgentPanel title="Assessment Agent">{{ attempt?.decision_reason }}</AgentPanel>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/module-placement"><PrimaryButton>Continue</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
