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

        <div class="anim-stagger relative mx-auto grid max-w-4xl gap-6 pt-5">
            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
            <div class="pointer-events-none absolute -right-16 bottom-20 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />

            <!-- Hero header -->
            <div class="flex flex-wrap items-center justify-center gap-4">
                <span class="flex size-20 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-xl shadow-blue-500/20 ring-1 ring-white/20">
                    <PartyPopper class="size-11" />
                </span>
                <h1 class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-center text-4xl font-black text-transparent lg:text-5xl">
                    Task 1 routing complete.
                </h1>
                <Sparkles class="size-8 text-primary/30" />
            </div>

            <!-- Metric cards -->
            <div class="grid gap-5 lg:grid-cols-2">
                <article
                    v-for="(card, idx) in metricCards"
                    :key="card.label"
                    class="anim-card flex items-center gap-6 rounded-[28px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30"
                >
                    <span
                        class="grid size-20 shrink-0 place-items-center rounded-2xl shadow-lg"
                        :class="idx === 0 ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-emerald-500/20' : 'bg-gradient-to-br from-violet-400 to-violet-600 text-white shadow-violet-500/20'"
                    >
                        <component :is="card.icon" class="size-10" />
                    </span>
                    <div>
                        <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">{{ card.label }}</p>
                        <p class="mt-1 text-4xl font-black leading-none text-slate-800">{{ card.value }}</p>
                    </div>
                </article>
            </div>

            <!-- Info cards -->
            <article
                v-for="(card, idx) in infoCards"
                :key="card.title"
                class="anim-card flex items-center gap-5 rounded-[28px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30"
            >
                <span
                    class="grid size-16 shrink-0 place-items-center rounded-2xl text-white shadow-lg ring-1 ring-white/20"
                    :class="[
                        idx === 0 ? 'bg-gradient-to-br from-sky-400 to-blue-600 shadow-blue-500/20' : '',
                        idx === 1 ? 'bg-gradient-to-br from-amber-300 to-amber-500 shadow-amber-500/20' : '',
                        idx === 2 ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-emerald-500/20' : '',
                    ]"
                >
                    <component :is="card.icon" class="size-8" />
                </span>
                <div>
                    <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">{{ card.title }}</p>
                    <p class="mt-1 text-[16px] text-slate-700" :class="card.strong ? 'font-black' : 'font-semibold'">{{ card.body }}</p>
                </div>
            </article>

            <!-- Sparkle decorations -->
            <span class="pointer-events-none absolute -right-6 top-8 text-4xl font-black text-primary/5">✦</span>
            <span class="pointer-events-none absolute -left-4 bottom-16 text-3xl font-black text-primary/5">✦</span>
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

<style scoped>
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
}

.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
.anim-stagger > *:nth-child(5) { animation-delay: 600ms; }
.anim-stagger > *:nth-child(6) { animation-delay: 750ms; }
.anim-stagger > *:nth-child(7) { animation-delay: 900ms; }
.anim-stagger > *:nth-child(8) { animation-delay: 1050ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

