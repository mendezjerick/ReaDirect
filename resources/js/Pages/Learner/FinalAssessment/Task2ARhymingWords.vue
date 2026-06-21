<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Check, Volume2, X } from 'lucide-vue-next';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import { useStepAssessment } from '../../../Composables/useStepAssessment';

const props = defineProps({
    items: Array,
    initialIndex: Number,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});

const form = useForm({ assessment_attempt_id: props.assessmentAttemptId, responses: [] });
const savedAnswers = Object.fromEntries((props.items ?? [])
    .filter((item) => item?.saved_response?.answer)
    .map((item) => [item.id, item.saved_response.answer]));
const step = useStepAssessment(props.items ?? [], {
    emptyMessage: 'Choose Yes or No.',
    initialIndex: props.initialIndex ?? 0,
});
Object.entries(savedAnswers).forEach(([id, answer]) => {
    step.answers[id] = answer;
});

const currentPayload = computed(() => step.currentItem.value?.payload ?? {});
const currentAnswer = computed(() => step.answers[step.currentItem.value?.id] ?? '');
const canUseDeveloperJumpControls = computed(() => props.assessmentMode?.canUseDeveloperJumpControls === true);
const firstFormError = computed(() => Object.values(form.errors ?? {})[0] ?? '');
const vivianPrompt = computed(() => {
    const script = currentPayload.value.vivian_prompt_script ?? currentPayload.value.audio_script ?? step.currentItem.value?.prompt ?? '';

    return String(script).toLowerCase().includes('rhyme') ? script : `${script}. Do these words rhyme?`;
});

const selectAnswer = (answer) => {
    step.answers[step.currentItem.value.id] = answer;
    step.feedback.value = '';
};

const submit = () => {
    if (!step.validateComplete()) return;

    form.responses = step.payload((item, answer) => ({
        assessment_attempt_item_id: item.id,
        answer,
    }));
    form.post('/final-assessment/task-2a/submit', {
        onError: (errors) => {
            const firstError = Object.values(errors ?? {})[0] ?? 'Choose Yes or No for each pair.';
            step.feedback.value = Array.isArray(firstError) ? firstError[0] : firstError;
        },
    });
};

const handlePrimary = () => {
    if (!step.validateCurrent()) return;
    if (step.isLast.value) return submit();
    step.goNext();
};
</script>

<template>
    <LearnerLayout :progress="42">
        <template #agent>
            <AgentSpeakerPanel
                compact
                show-audio-button
                agent-type="assessment"
                state="speaking"
                presentation="assessment-task"
                :message="vivianPrompt"
            />
        </template>

        <section class="mx-auto grid w-full max-w-2xl gap-5">
            <div class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3 px-1">
                    <p class="inline-flex items-center gap-2 text-[15px] font-black text-slate-700">
                        <span class="grid size-8 place-items-center rounded-lg bg-primary text-sm font-black text-white">
                            {{ step.currentIndex.value + 1 }}
                        </span>
                        Task 2A item {{ step.currentIndex.value + 1 }} of {{ items.length }}
                    </p>
                    <p class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-3 py-1.5 text-[13px] font-black text-primary ring-1 ring-primary/10">
                        <Volume2 class="size-4" />
                        Vivian prompt
                    </p>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div class="h-full rounded-full bg-primary transition-all duration-300" :style="{ width: `${step.progressPercent.value}%` }" />
                </div>
            </div>

            <div class="rounded-[28px] border border-slate-200/80 bg-white p-6 text-center shadow-xl shadow-slate-200/30">
                <p class="text-[13px] font-black uppercase tracking-widest text-slate-400">Word pair</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <span class="rounded-2xl bg-slate-50 px-5 py-5 text-4xl font-black text-slate-800 ring-1 ring-slate-200/70">
                        {{ currentPayload.word_1 }}
                    </span>
                    <span class="text-2xl font-black text-slate-300">/</span>
                    <span class="rounded-2xl bg-slate-50 px-5 py-5 text-4xl font-black text-slate-800 ring-1 ring-slate-200/70">
                        {{ currentPayload.word_2 }}
                    </span>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <button
                    type="button"
                    class="grid min-h-36 place-items-center rounded-[24px] border-2 bg-white p-6 text-3xl font-black shadow-lg transition"
                    :class="currentAnswer === 'yes' ? 'border-emerald-500 text-emerald-700 ring-4 ring-emerald-100' : 'border-slate-200 text-slate-700 hover:border-emerald-300'"
                    @click="selectAnswer('yes')"
                >
                    <span class="grid justify-items-center gap-3">
                        <Check class="size-12 stroke-[3]" />
                        Yes
                    </span>
                </button>
                <button
                    type="button"
                    class="grid min-h-36 place-items-center rounded-[24px] border-2 bg-white p-6 text-3xl font-black shadow-lg transition"
                    :class="currentAnswer === 'no' ? 'border-rose-500 text-rose-700 ring-4 ring-rose-100' : 'border-slate-200 text-slate-700 hover:border-rose-300'"
                    @click="selectAnswer('no')"
                >
                    <span class="grid justify-items-center gap-3">
                        <X class="size-12 stroke-[3]" />
                        No
                    </span>
                </button>
            </div>

            <p v-if="firstFormError" class="rounded-2xl bg-rose-50 px-4 py-3 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">{{ firstFormError }}</p>
            <p v-if="step.feedback.value" class="rounded-2xl bg-amber-50 px-4 py-3 text-sm font-black text-amber-700 ring-1 ring-amber-200/60">{{ step.feedback.value }}</p>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton v-if="canUseDeveloperJumpControls && !step.isFirst.value" @click="step.goBack">Developer QA: Back</SecondaryButton>
                <span v-else />
                <PrimaryButton :disabled="form.processing || !step.isCurrentAnswered.value" :class="{ 'opacity-70': !step.isCurrentAnswered.value }" @click="handlePrimary">
                    {{ step.isLast.value ? 'Save answers' : 'Next' }}
                </PrimaryButton>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>
