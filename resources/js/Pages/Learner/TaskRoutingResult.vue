<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight, ClipboardCheck, Lightbulb, Medal, PartyPopper, Rocket, Signpost, Sparkles } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({ attempt: Object, route: Object });

const requiresTask2A = props.route?.requires_task_2a ?? ((props.attempt?.task_1_score ?? 0) <= 6);
const nextHref = requiresTask2A ? '/learner/diagnostic/task-2a' : '/learner/diagnostic/task-2b';
const nextTitle = requiresTask2A ? 'Task 2A: Rhyming Words' : 'Task 2B: Word in Sentence';
const nextSummary = requiresTask2A
    ? 'You will continue to the rhyming task next.'
    : 'Task 2A is skipped, and you will continue directly to the sentence-reading task.';
const taskTwoAScoreLabel = requiresTask2A ? 'Required next' : `${props.route?.assigned_task_2a_score ?? props.attempt?.task_2a_score ?? 10}/10`;
const metricCards = [
    {
        label: 'Task 1 Score',
        value: `${props.attempt?.task_1_score ?? 0}/10`,
        icon: ClipboardCheck,
    },
    {
        label: 'Task 2A Status',
        value: taskTwoAScoreLabel,
        icon: Medal,
    },
];
const infoCards = [
    { title: 'Next Task', body: nextTitle, icon: Signpost, strong: true },
    { title: 'Routing Reason', body: props.attempt?.decision_reason, icon: Lightbulb },
    { title: 'What Happens Now', body: nextSummary, icon: Rocket },
];
</script>

<template>
    <LearnerLayout :progress="72" diagnostic-step="task-1">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="pointing"
                presentation="routing"
                :message="requiresTask2A ? 'I checked the letter score. Task 2A is required before we move on.' : 'I checked the letter score. Task 2A is skipped, so we can move straight to the next reading task.'"
            />
        </template>
        <div class="routing-result relative mx-auto grid max-w-4xl gap-6 pt-5">
            <div class="flex flex-wrap items-center justify-center gap-4">
                <span class="grid size-20 place-items-center rounded-full bg-primary text-white shadow-xl shadow-primary/25">
                    <PartyPopper class="size-11" />
                </span>
                <h1 class="text-center text-4xl font-black text-slate-950 md:text-5xl">Task 1 routing complete.</h1>
                <Sparkles class="size-8 fill-accent text-accent" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <article
                    v-for="card in metricCards"
                    :key="card.label"
                    class="flex items-center gap-6 rounded-[22px] border border-blue-100 bg-surface p-5 shadow-lg shadow-primary/10"
                >
                    <span class="grid size-24 shrink-0 place-items-center rounded-full bg-blue-100 text-primary">
                        <component :is="card.icon" class="size-14" />
                    </span>
                    <div>
                        <p class="text-xl font-black text-primary">{{ card.label }}</p>
                        <p class="mt-2 text-4xl font-black leading-none text-slate-950">{{ card.value }}</p>
                        <p class="mt-2 text-sm font-bold text-slate-500">from last month</p>
                    </div>
                </article>
            </div>

            <article
                v-for="card in infoCards"
                :key="card.title"
                class="flex items-center gap-5 rounded-[22px] border border-blue-100 bg-surface p-5 shadow-lg shadow-primary/10"
            >
                <span class="grid size-20 shrink-0 place-items-center rounded-full bg-primary text-white shadow-lg shadow-primary/20">
                    <component :is="card.icon" class="size-10" />
                </span>
                <div>
                    <p class="text-xl font-black text-primary">{{ card.title }}</p>
                    <p class="mt-2 text-xl text-slate-950" :class="card.strong ? 'font-black' : 'font-bold'">{{ card.body }}</p>
                </div>
            </article>
        </div>
        <BottomActionBar>
            <Link :href="nextHref">
                <PrimaryButton>
                    <span class="inline-flex items-center gap-4">
                        Continue
                        <ChevronRight class="size-7 stroke-[3]" />
                    </span>
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
