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
    <LearnerLayout :progress="25" diagnostic-step="intro">
        <template #agent>
            <AgentSpeakerPanel agent-type="assessment" state="speaking" message="We will do a short reading check together. I will guide each step." show-audio-button />
        </template>

        <div class="anim-stagger relative mx-auto grid max-w-2xl gap-6">
            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
            <div class="pointer-events-none absolute -right-16 bottom-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />

            <!-- Prompt card -->
            <PromptCard label="Diagnostic reading check" prompt="Ready to read?" size="word" />

            <!-- Agent panel -->
            <AgentPanel title="Miss Vivian">We will do short reading tasks. Take your time and try your best.</AgentPanel>

            <!-- Developer retest warning -->
            <div
                v-if="props.developerRetest?.enabled"
                class="rounded-2xl border border-amber-200/60 bg-amber-50 p-5 text-left ring-1 ring-amber-200/40"
            >
                <p class="text-[14px] font-black text-amber-700">
                    Developer testing only. This starts a new sandbox diagnostic attempt for QA testing and preserves previous attempts.
                </p>
                <SecondaryButton class="mt-3" :disabled="retestForm.processing" @click="startDeveloperRetest">
                    Start New Developer Test Attempt
                </SecondaryButton>
            </div>

            <!-- Sparkle decorations -->
            <span class="pointer-events-none absolute -right-6 top-4 text-4xl font-black text-primary/5">✦</span>
        </div>

        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="start">
                <span class="inline-flex items-center gap-2">
                    🚀 Start diagnostic
                </span>
            </PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}

.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
.anim-stagger > *:nth-child(5) { animation-delay: 600ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
