<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import RecordingButton from '../../Components/RecordingButton.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({ passage: Object });

const form = useForm({ incorrect_words: 0 });
const submit = () => form.post('/learner/diagnostic/passage');
</script>

<template>
    <LearnerLayout :progress="78">
        <div class="mx-auto grid max-w-3xl gap-6">
            <div class="flex items-center justify-between">
                <StatusBadge status="50 words" />
                <StatusBadge status="Max 60 seconds" variant="warning" />
            </div>
            <section class="rounded-[32px] border border-border bg-surface p-7 shadow-xl shadow-primary/10">
                <p class="text-3xl font-black leading-relaxed text-text">{{ passage.prompt }}</p>
            </section>
            <div class="grid gap-4 rounded-[28px] border border-border bg-surface p-5 shadow-lg shadow-primary/10 md:grid-cols-[160px_1fr]">
                <RecordingButton state="ready" />
                <label class="grid content-center gap-2 text-lg font-black text-text">
                    Incorrect words for Phase 2 manual check
                    <input v-model="form.incorrect_words" type="number" min="0" max="50" class="rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none">
                </label>
            </div>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="submit">Continue</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
