<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

const props = defineProps({
    passage: Object,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});

const form = useForm({ incorrect_words: 0, audio: null, duration_seconds: null });
const audioFile = ref(null);
const canUseManualFallback = computed(() => props.assessmentMode?.canUseManualFallback === true);
const hasIncorrectWords = () => form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
const canSubmit = computed(() => Boolean(form.audio) && (canUseManualFallback.value ? hasIncorrectWords() : true));
const rememberAudio = (file) => {
    audioFile.value = file;
    form.audio = file;
    form.duration_seconds = file.durationSeconds ?? null;
};
const clearAudio = () => {
    audioFile.value = null;
    form.audio = null;
    form.duration_seconds = null;
};
const submit = () => form.post('/final-assessment/passage/submit', { forceFormData: true });
</script>

<template>
    <LearnerLayout :progress="72">
        <template #agent>
            <AgentSpeakerPanel agent-type="assessment" state="listening" message="This is your final reading check. Read the passage aloud and try your best." />
        </template>
        <div class="mx-auto grid max-w-2xl gap-3">
            <div class="flex items-center justify-between">
                <StatusBadge status="50 words" />
                <StatusBadge status="Max 60 seconds" variant="warning" />
            </div>
            <section class="max-h-[34vh] overflow-y-auto rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10 lg:max-h-[42vh]">
                <p class="text-2xl font-black leading-relaxed text-text md:text-[28px]">{{ passage.prompt }}</p>
            </section>
            <div class="grid gap-3 rounded-[24px] border border-border bg-surface p-4 shadow-lg shadow-primary/10 md:grid-cols-[220px_1fr]">
                <AudioRecorder compact :max-duration-seconds="60" label="Passage voice" @recorded="rememberAudio" @cleared="clearAudio" />
                <label v-if="canUseManualFallback" class="grid content-center gap-2 text-lg font-black text-text">
                    Developer QA: Incorrect Words Override
                    <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none">
                </label>
                <div v-else class="grid content-center rounded-2xl border-2 border-border bg-background px-4 py-5 text-lg font-black text-muted">
                    Record your reading to continue.
                </div>
            </div>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing || !canSubmit" @click="submit">Continue</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
