<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import PromptCard from '../../../Components/PromptCard.vue';

const form = useForm({});
const start = () => form.post('/final-assessment/start');
</script>

<template>
    <LearnerLayout :progress="10">
        <template #agent>
            <AgentSpeakerPanel agent-type="assessment" state="speaking" message="This is your final reading check. Do your best, one step at a time." />
        </template>

        <section class="mx-auto grid max-w-2xl gap-5 text-center">
            <h1 class="text-4xl font-black text-text">Final Reassessment</h1>
            <PromptCard label="Ready?" prompt="Let us check how your reading has grown." size="sentence" />
            <p class="text-lg font-bold text-muted">You will do the same reading tasks as before. Your teacher will see how you improved.</p>
        </section>

        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="start">Start final check</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
