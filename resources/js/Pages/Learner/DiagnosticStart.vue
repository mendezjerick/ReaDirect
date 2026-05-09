<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({
    developerRetest: Object,
});
const form = useForm({});
const retestForm = useForm({});
const start = () => form.post('/learner/diagnostic/start');
const startDeveloperRetest = () => retestForm.post('/learner/diagnostic/developer-retest');
</script>

<template>
    <LearnerLayout :progress="25">
        <template #agent>
            <AgentSpeakerPanel agent-type="assessment" state="speaking" message="We will do a short reading check together. I will guide each step." show-audio-button />
        </template>
        <div class="mx-auto grid max-w-2xl gap-6">
            <PromptCard label="Diagnostic reading check" prompt="Ready to read?" size="word" />
            <AgentPanel title="Miss Vivian">We will do short reading tasks. Take your time and try your best.</AgentPanel>
            <div v-if="props.developerRetest?.enabled" class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-left">
                <p class="text-sm font-black text-warning">Developer testing only. This starts a new sandbox diagnostic attempt for QA testing and preserves previous attempts.</p>
                <SecondaryButton class="mt-3" :disabled="retestForm.processing" @click="startDeveloperRetest">Start New Developer Test Attempt</SecondaryButton>
            </div>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="start">Start diagnostic</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
