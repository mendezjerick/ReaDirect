<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
</script>

<template>
    <LearnerLayout :progress="30">
        <div class="mx-auto grid max-w-2xl gap-6">
            <PromptCard label="Reading check" prompt="Say each letter clearly." size="sentence" />
            <AgentPanel title="Miss Vivian">I will show one letter at a time.</AgentPanel>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/task-1"><PrimaryButton>Start</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
