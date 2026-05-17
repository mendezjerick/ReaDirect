<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BarChart3, BookOpen, CheckCircle2, Target, Type } from 'lucide-vue-next';
import LearnerSimplePageShell from '../../Components/Learner/LearnerSimplePageShell.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    latestAttempt: { type: Object, default: null },
    flowState: { type: Object, default: null },
});

const score = (value) => Number(value ?? 0);
const readingAccuracy = computed(() => {
    const value = props.latestAttempt?.reading_accuracy;
    if (value == null) return 0;
    const number = Number(value);
    return Math.round(number <= 1 ? number * 100 : number);
});

const progressItems = computed(() => [
    { label: 'Task 1 Letters', value: `${score(props.latestAttempt?.task_1_score)}/10`, icon: Type, detail: 'Letter recognition and sounds' },
    { label: 'Task 2A Rhymes', value: `${score(props.latestAttempt?.task_2a_score)}/10`, icon: Target, detail: 'Rhyme awareness' },
    { label: 'Task 2B Words', value: `${score(props.latestAttempt?.task_2b_score)}/10`, icon: BookOpen, detail: 'Words inside sentences' },
    { label: 'Reading Accuracy', value: `${readingAccuracy.value}%`, icon: BarChart3, detail: 'Passage reading check' },
]);
</script>

<template>
    <LearnerSimplePageShell
        :learner="learner"
        title="Progress"
        subtitle="Your latest reading check details"
        active="progress"
    >
        <section class="grid gap-4 lg:grid-cols-[1fr_22rem]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-black uppercase text-primary">Latest Progress</p>
                        <h2 class="mt-1 text-2xl font-black text-text">Reading skills overview</h2>
                    </div>
                    <Link
                        :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-black text-white shadow-md shadow-primary/30"
                    >
                        Continue
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="item in progressItems"
                        :key="item.label"
                        class="rounded-2xl border border-blue-100 bg-blue-50/40 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-500">{{ item.label }}</p>
                                <p class="mt-2 text-3xl font-black text-primary">{{ item.value }}</p>
                            </div>
                            <span class="grid size-11 place-items-center rounded-xl bg-white text-primary shadow-sm">
                                <component :is="item.icon" class="size-6" />
                            </span>
                        </div>
                        <p class="mt-3 text-sm font-bold text-slate-600">{{ item.detail }}</p>
                    </article>
                </div>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="grid size-12 place-items-center rounded-xl bg-success/10 text-success">
                    <CheckCircle2 class="size-7" />
                </span>
                <h2 class="mt-4 text-xl font-black text-text">Current step</h2>
                <p class="mt-2 text-sm font-bold leading-relaxed text-slate-600">
                    {{ flowState?.message ?? 'Continue your reading path from the dashboard.' }}
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
