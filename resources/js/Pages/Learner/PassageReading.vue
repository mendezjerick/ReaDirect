<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AudioRecorder from '../../Components/Learner/AudioRecorder.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({ passage: Object });

const form = useForm({ incorrect_words: 0, audio: null, duration_seconds: null });
const audioFile = ref(null);
const hasIncorrectWords = () => form.incorrect_words !== '' && form.incorrect_words !== null && Number(form.incorrect_words) >= 0;
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
const submit = () => form.post('/learner/diagnostic/passage', { forceFormData: true });
</script>

<template>
    <LearnerLayout :progress="78">
        <template #agent>
            <AgentSpeakerPanel agent-type="assessment" state="listening" message="Read the passage aloud. Enter the number of words to review before continuing." />
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
                <AudioRecorder
                    compact
                    :max-duration-seconds="60"
                    label="Passage voice"
                    @recorded="rememberAudio"
                    @cleared="clearAudio"
                />
                <label class="grid content-center gap-2 text-lg font-black text-text">
                    Incorrect words for Phase 2 manual check
                    <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-2xl border-2 border-border px-4 py-3 text-lg font-black focus:border-primary focus:outline-none">
                </label>
            </div>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing || !hasIncorrectWords()" @click="submit">Continue</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
