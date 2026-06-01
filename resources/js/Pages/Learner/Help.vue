<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, HelpCircle, Mic, RotateCcw, Volume2, Sparkles } from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

defineProps({
    learner: { type: Object, default: null },
    latestAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
});

const helpCards = [
    {
        title: 'Recording your voice',
        detail: 'Wait for the cue, speak clearly, then listen before submitting.',
        icon: Mic,
        gradient: 'from-sky-400 to-blue-500',
        shadow: 'shadow-blue-500/20',
    },
    {
        title: 'Hearing the guide',
        detail: 'Use the speaker or replay button when a guide message does not play automatically.',
        icon: Volume2,
        gradient: 'from-violet-400 to-purple-500',
        shadow: 'shadow-violet-500/20',
    },
    {
        title: 'Trying again',
        detail: 'If the recording is unclear, use Try Again or Record Again before moving on.',
        icon: RotateCcw,
        gradient: 'from-amber-400 to-orange-500',
        shadow: 'shadow-orange-500/20',
    },
    {
        title: 'Finding your lesson',
        detail: 'Open My Learning when you want to return to your current module.',
        icon: BookOpen,
        gradient: 'from-emerald-400 to-green-500',
        shadow: 'shadow-emerald-500/20',
    },
];
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Help"
        subtitle="Quick help for reading activities"
        active="help"
    >
        <section class="grid gap-5 lg:grid-cols-[1fr_22rem] xl:gap-6">
            <!-- Main Help Card -->
            <div class="rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 xl:p-8">
                <!-- Section Header -->
                <div class="flex items-center gap-4">
                    <span class="grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20 xl:size-16">
                        <HelpCircle class="size-7 xl:size-8" stroke-width="2.5" />
                    </span>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-widest text-blue-600 xl:text-[12px]">Need Help?</p>
                        <h2 class="text-2xl font-black text-slate-800 xl:text-3xl">Common things to check</h2>
                    </div>
                </div>

                <!-- Help Cards Grid -->
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:mt-8 xl:gap-5">
                    <article
                        v-for="card in helpCards"
                        :key="card.title"
                        class="group rounded-[24px] border border-slate-200/80 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg xl:p-6"
                    >
                        <span
                            class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br text-white shadow-md ring-1 ring-white/20 xl:size-14"
                            :class="[card.gradient, card.shadow]"
                        >
                            <component :is="card.icon" class="size-6 xl:size-7" stroke-width="2.5" />
                        </span>
                        <h3 class="mt-4 text-lg font-black text-slate-800 xl:mt-5 xl:text-xl">{{ card.title }}</h3>
                        <p class="mt-2 text-sm font-semibold leading-relaxed text-slate-500 xl:text-base">{{ card.detail }}</p>
                    </article>
                </div>
            </div>

            <!-- Next Step Sidebar -->
            <aside class="rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 lg:self-start xl:p-8">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20">
                        <Sparkles class="size-5" stroke-width="2.5" />
                    </span>
                    <h2 class="text-xl font-black text-slate-800 xl:text-2xl">Next step</h2>
                </div>
                <p class="mt-4 text-sm font-semibold leading-relaxed text-slate-500 xl:text-base">
                    {{ flowState?.message ?? 'Return to the dashboard and continue your reading path.' }}
                </p>
                <Link
                    :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                    class="group mt-6 inline-flex w-full items-center justify-center gap-3 rounded-[22px] bg-gradient-to-br from-sky-400 to-blue-600 px-6 py-4 text-base font-black text-white shadow-xl shadow-blue-500/25 ring-1 ring-white/20 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-2xl active:scale-[0.98] xl:text-lg"
                >
                    {{ flowState?.primary_action_label ?? 'Continue' }}
                    <ArrowRight class="size-5 stroke-[3] transition-transform group-hover:translate-x-1" />
                </Link>
            </aside>
        </section>
    </LearnerSimplePageShell>
</template>
