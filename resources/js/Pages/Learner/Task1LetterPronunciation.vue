<script setup>
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import RecordingButton from '../../Components/RecordingButton.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({ items: Array });
const answers = reactive(Object.fromEntries(props.items.map((item) => [item.id, ''])));
const form = useForm({ responses: [] });
const answered = computed(() => Object.values(answers).filter((answer) => answer.trim()).length);

const submit = () => {
    form.responses = props.items.map((item) => ({
        assessment_attempt_item_id: item.id,
        answer: answers[item.id],
    }));
    form.post('/learner/diagnostic/task-1');
};
</script>

<template>
    <LearnerLayout :progress="35">
        <div class="mx-auto grid max-w-3xl gap-5">
            <div class="flex items-center justify-between">
                <StatusBadge :status="`${answered} of ${items.length} answered`" />
                <StatusBadge status="Manual input" variant="primary" />
            </div>
            <div class="grid gap-4">
                <section v-for="item in items" :key="item.id" class="rounded-[28px] border border-border bg-surface p-5 shadow-lg shadow-primary/10">
                    <PromptCard :label="`Letter ${item.sequence}`" :prompt="item.prompt" size="letter" />
                    <div class="mt-5 grid gap-3 md:grid-cols-[160px_1fr]">
                        <RecordingButton state="ready" />
                        <label class="grid content-center gap-2 text-lg font-black text-text">
                            What did the learner say?
                            <input v-model="answers[item.id]" class="rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none" placeholder="Type answer">
                        </label>
                    </div>
                </section>
            </div>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="submit">Check letters</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
