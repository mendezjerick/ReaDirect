<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, HelpCircle, Mic, RotateCcw, Volume2 } from 'lucide-vue-next';
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
    },
    {
        title: 'Hearing the guide',
        detail: 'Use the speaker or replay button when a guide message does not play automatically.',
        icon: Volume2,
    },
    {
        title: 'Trying again',
        detail: 'If the recording is unclear, use Try Again or Record Again before moving on.',
        icon: RotateCcw,
    },
    {
        title: 'Finding your lesson',
        detail: 'Open My Learning when you want to return to your current module.',
        icon: BookOpen,
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
        <section class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-xl bg-blue-50 text-primary">
                        <HelpCircle class="size-7" />
                    </span>
                    <div>
                        <p class="text-sm font-black uppercase text-primary">Need Help?</p>
                        <h2 class="text-2xl font-black text-text">Common things to check</h2>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="card in helpCards"
                        :key="card.title"
                        class="rounded-2xl border border-blue-100 bg-blue-50/40 p-4"
                    >
                        <span class="grid size-11 place-items-center rounded-xl bg-white text-primary shadow-sm">
                            <component :is="card.icon" class="size-6" />
                        </span>
                        <h3 class="mt-4 text-lg font-black text-text">{{ card.title }}</h3>
                        <p class="mt-2 text-sm font-bold leading-relaxed text-slate-600">{{ card.detail }}</p>
                    </article>
                </div>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black text-text">Next step</h2>
                <p class="mt-2 text-sm font-bold leading-relaxed text-slate-600">
                    {{ flowState?.message ?? 'Return to the dashboard and continue your reading path.' }}
                </p>
                <Link
                    :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-black text-white shadow-md shadow-primary/30"
                >
                    {{ flowState?.primary_action_label ?? 'Continue' }}
                    <ArrowRight class="size-4" />
                </Link>
            </aside>
        </section>
    </LearnerSimplePageShell>
</template>
