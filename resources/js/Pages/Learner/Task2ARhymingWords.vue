<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PromptCard from '../../Components/PromptCard.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import ModuleProgressBar from '../../Components/ModuleProgressBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';

const props = defineProps({ items: Array });
const step = useStepAssessment(props.items, { emptyMessage: 'Let us answer this first.' });
const form = useForm({ responses: [] });

const submit = () => {
    if (!step.validateCurrent()) return;

    form.responses = step.payload((item, answer) => ({ assessment_attempt_item_id: item.id, answer }));
    form.post('/learner/diagnostic/task-2a');
};

const handlePrimary = () => {
    if (step.isLast.value) {
        submit();
        return;
    }

    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="48">
        <template #agent>
            <AgentSpeakerPanel compact agent-type="assessment" state="listening" message="Say one word that rhymes with this word." />
        </template>

        <section class="mx-auto grid max-w-2xl gap-4">
            <StatusBadge :status="`Rhyme ${step.currentIndex.value + 1} of ${items.length}`" />
            <ModuleProgressBar :value="step.progressPercent.value" />
            <PromptCard label="Say a word that rhymes with" :prompt="step.currentItem.value.prompt" size="word" />
            <div class="rounded-[28px] border border-border bg-surface p-5 shadow-lg shadow-primary/10">
                <input v-model="step.answers[step.currentItem.value.id]" class="w-full rounded-2xl border-2 border-border px-5 py-4 text-xl font-black focus:border-primary focus:outline-none" placeholder="Type a rhyming word">
                <p v-if="step.feedback.value" class="mt-4 rounded-2xl bg-accent px-4 py-3 text-lg font-black text-text">{{ step.feedback.value }}</p>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="!step.isFirst.value" @click="step.goBack">Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Check rhymes' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
