<script setup>
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({ questions: Array });
const answers = reactive(Object.fromEntries(props.questions.map((question) => [question.id, ''])));
const form = useForm({ responses: [] });

const submit = () => {
    form.responses = props.questions.map((question) => ({ question_id: question.id, answer: answers[question.id] }));
    form.post('/learner/diagnostic/comprehension');
};
</script>

<template>
    <LearnerLayout :progress="86">
        <div class="mx-auto grid max-w-3xl gap-5">
            <StatusBadge status="5 questions" />
            <section v-for="question in questions" :key="question.id" class="rounded-[28px] border border-border bg-surface p-6 shadow-lg shadow-primary/10">
                <p class="text-2xl font-black text-text">{{ question.sequence }}. {{ question.question_text }}</p>
                <div class="mt-4 grid gap-3">
                    <label v-for="(choice, key) in question.choices" :key="key" class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-border p-4 text-lg font-black hover:border-primary">
                        <input v-model="answers[question.id]" type="radio" :name="question.id" :value="choice" class="size-5">
                        {{ choice }}
                    </label>
                </div>
            </section>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="submit">Check answers</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
