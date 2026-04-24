<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
</script>

<template>
    <LearnerLayout :progress="70">
        <div class="mx-auto grid max-w-2xl gap-6">
            <PromptCard label="Reading passage" prompt="Read aloud." size="sentence" />
            <AgentPanel title="Assessment Agent">Read the passage clearly. Then answer five short questions.</AgentPanel>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/passage"><PrimaryButton>Start passage</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
