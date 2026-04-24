<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const form = useForm({});
const start = () => form.post('/learner/diagnostic/start');
</script>

<template>
    <LearnerLayout :progress="25">
        <div class="mx-auto grid max-w-2xl gap-6">
            <PromptCard label="Diagnostic reading check" prompt="Ready to read?" size="word" />
            <AgentPanel title="Assessment Agent">We will do short reading tasks. Take your time and try your best.</AgentPanel>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="start">Start diagnostic</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
