<script setup>
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({ items: Array });
const answers = reactive(Object.fromEntries(props.items.map((item) => [item.id, ''])));
const form = useForm({ responses: [] });
const answered = computed(() => Object.values(answers).filter((answer) => answer.trim()).length);

const submit = () => {
    form.responses = props.items.map((item) => ({ assessment_attempt_item_id: item.id, answer: answers[item.id] }));
    form.post('/learner/diagnostic/task-2a');
};
</script>

<template>
    <LearnerLayout :progress="48">
        <div class="mx-auto grid max-w-3xl gap-5">
            <StatusBadge :status="`${answered} of ${items.length} rhymes answered`" />
            <section v-for="item in items" :key="item.id" class="rounded-[28px] border border-border bg-surface p-5 shadow-lg shadow-primary/10">
                <PromptCard :label="`Say a word that rhymes with`" :prompt="item.prompt" size="word" />
                <input v-model="answers[item.id]" class="mt-5 w-full rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none" placeholder="Type a rhyming word">
            </section>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="submit">Check rhymes</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
