<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import PromptCard from '../../Components/PromptCard.vue';

const form = useForm({ learner_code: 'RD-1001' });
const submit = () => form.post('/learner/access');
</script>

<template>
    <LearnerLayout :progress="10">
        <div class="mx-auto grid max-w-xl gap-6">
            <PromptCard label="Welcome reader" prompt="Ready?" size="word" />
            <form class="rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10" @submit.prevent="submit">
                <label for="learner_code" class="text-lg font-black text-text">Learner code</label>
                <input id="learner_code" v-model="form.learner_code" class="mt-3 w-full rounded-2xl border-2 border-border px-5 py-4 text-xl font-black uppercase tracking-wide focus:border-primary focus:outline-none">
                <p v-if="form.errors.learner_code" class="mt-2 text-base font-bold text-danger">{{ form.errors.learner_code }}</p>
                <PrimaryButton class="mt-5 w-full" type="submit" :disabled="form.processing">Continue</PrimaryButton>
            </form>
        </div>
    </LearnerLayout>
</template>
