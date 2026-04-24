<script setup>
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({ items: Array });
const answers = reactive(Object.fromEntries(props.items.map((item) => [item.id, ''])));
const form = useForm({ responses: [] });
const answered = computed(() => Object.values(answers).filter((answer) => answer.trim()).length);

const parts = (item) => {
    const target = item.payload?.target_word ?? '';
    if (!target) return [item.prompt];
    return item.prompt.split(new RegExp(`(${target})`, 'i'));
};

const submit = () => {
    form.responses = props.items.map((item) => ({ assessment_attempt_item_id: item.id, answer: answers[item.id] }));
    form.post('/learner/diagnostic/task-2b');
};
</script>

<template>
    <LearnerLayout :progress="58">
        <div class="mx-auto grid max-w-3xl gap-5">
            <StatusBadge :status="`${answered} of ${items.length} words answered`" />
            <section v-for="item in items" :key="item.id" class="rounded-[28px] border border-border bg-surface p-6 shadow-lg shadow-primary/10">
                <p class="text-lg font-black text-muted">Read the sentence</p>
                <p class="mt-3 text-3xl font-black leading-snug text-text">
                    <template v-for="(part, index) in parts(item)" :key="index">
                        <mark v-if="part.toLowerCase() === (item.payload?.target_word ?? '').toLowerCase()" class="rounded-xl bg-accent px-2">{{ part }}</mark>
                        <span v-else>{{ part }}</span>
                    </template>
                </p>
                <input v-model="answers[item.id]" class="mt-5 w-full rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none" placeholder="Type the target word read">
            </section>
        </div>
        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="submit">Check words</PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>
