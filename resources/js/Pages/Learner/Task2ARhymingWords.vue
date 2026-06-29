<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Check, Volume2, X } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from '../../Components/Learner/AssessmentTaskWorkspace.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import { useStepAssessment } from '../../Composables/useStepAssessment';

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
const vivianPrompt = 'Listen to both words carefully. Then choose Yes if they rhyme, or No if they do not rhyme.';

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
    form.post('/learner/diagnostic/task-2a', {
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
    <LearnerLayout assessment-task>
        <AssessmentTaskWorkspace
            agent-type="assessment"
            state="speaking"
            :agent-message="vivianPrompt"
            agent-line-key="vivian.task2a.rhyme_prompt_intro"
            :progress="step.progressPercent.value"
            :primary-label="step.isLast.value ? 'Save answers' : 'Next'"
            :primary-disabled="form.processing || !step.isCurrentAnswered.value"
            @primary="handlePrimary"
        >
            <template #prompt>
                <div class="flex h-full flex-col items-center justify-center gap-6 p-4">
                    <p class="text-sm font-black uppercase tracking-widest text-slate-400">Word pair</p>
                    <div class="flex items-center gap-4">
                        <span class="rounded-2xl bg-white px-6 py-6 text-5xl font-black text-slate-800 shadow-md">
                            {{ currentPayload.word_1 }}
                        </span>
                        <span class="text-3xl font-black text-slate-300">/</span>
                        <span class="rounded-2xl bg-white px-6 py-6 text-5xl font-black text-slate-800 shadow-md">
                            {{ currentPayload.word_2 }}
                        </span>
                    </div>
                </div>
            </template>

            <template #recorder>
                <div class="assessment-hold-recorder flex flex-1 h-full min-h-0 w-full flex-col">
                    <div class="task2-panel flex flex-1 h-full min-h-0 flex-col">
                        <div class="task2-face flex flex-1 flex-row items-stretch justify-center gap-3 p-3 sm:gap-4 sm:p-5">
                            <button
                                type="button"
                                class="group flex flex-1 flex-col items-center justify-center gap-3 rounded-[20px] border-[2.5px] bg-white text-xl font-black shadow-sm transition-all sm:text-2xl"
                                :class="currentAnswer === 'yes' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 ring-4 ring-emerald-500/20' : 'border-slate-200 text-slate-400 hover:border-emerald-300 hover:text-emerald-500'"
                                @click="selectAnswer('yes')"
                            >
                                <div class="grid place-items-center rounded-full bg-emerald-100 p-2 text-emerald-600 transition-transform group-hover:scale-110" :class="currentAnswer === 'yes' ? 'scale-110' : 'opacity-60 grayscale'">
                                    <Check class="size-6 stroke-[4] sm:size-8" />
                                </div>
                                Yes
                            </button>
                            <button
                                type="button"
                                class="group flex flex-1 flex-col items-center justify-center gap-3 rounded-[20px] border-[2.5px] bg-white text-xl font-black shadow-sm transition-all sm:text-2xl"
                                :class="currentAnswer === 'no' ? 'border-rose-500 bg-rose-50 text-rose-700 ring-4 ring-rose-500/20' : 'border-slate-200 text-slate-400 hover:border-rose-300 hover:text-rose-500'"
                                @click="selectAnswer('no')"
                            >
                                <div class="grid place-items-center rounded-full bg-rose-100 p-2 text-rose-600 transition-transform group-hover:scale-110" :class="currentAnswer === 'no' ? 'scale-110' : 'opacity-60 grayscale'">
                                    <X class="size-6 stroke-[4] sm:size-8" />
                                </div>
                                No
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #status>
                <p v-if="firstFormError" class="w-full rounded-2xl bg-rose-50 px-4 py-3 text-sm font-black text-rose-600 ring-1 ring-rose-200/60">{{ firstFormError }}</p>
                <p v-if="step.feedback.value" class="w-full rounded-2xl bg-amber-50 px-4 py-3 text-sm font-black text-amber-700 ring-1 ring-amber-200/60">{{ step.feedback.value }}</p>
            </template>

            <template v-if="canUseDeveloperJumpControls && !step.isFirst.value" #qa>
                <SecondaryButton @click="step.goBack">Developer QA: Back</SecondaryButton>
            </template>
        </AssessmentTaskWorkspace>
    </LearnerLayout>
</template>

<style scoped>
.task2-panel {
    min-width: 0;
    min-height: 0;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: var(--rd-radius-frame);
    background: var(--rd-story-surface);
    color: var(--rd-text-main);
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
    padding: 10px 12px 14px;
}

.task2-face {
    min-width: 0;
    min-height: 0;
    border: 1.5px solid var(--rd-face-border);
    border-radius: var(--rd-radius-face);
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}
</style>
