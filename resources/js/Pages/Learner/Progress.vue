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
        <section class="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
            <!-- Main Progress Area -->
            <div class="anim-fade-down rounded-[36px] border border-slate-200/60 bg-white/80 p-6 shadow-xl shadow-slate-200/40 backdrop-blur-md lg:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-[13px] font-black uppercase tracking-widest text-primary">Latest Progress</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-800 lg:text-3xl">Reading skills overview</h2>
                    </div>
                </div>

                <div class="anim-stagger mt-8 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="item in progressItems"
                        :key="item.label"
                        class="relative flex flex-col rounded-[28px] bg-gradient-to-br from-blue-50 to-indigo-50/50 p-5 ring-1 ring-blue-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-blue-500/10"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[14px] font-black text-slate-500">{{ item.label }}</p>
                                <p class="mt-2 text-4xl font-black text-blue-600">{{ item.value }}</p>
                            </div>
                            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white text-blue-500 shadow-md shadow-blue-500/10">
                                <component :is="item.icon" class="size-7" />
                            </span>
                        </div>
                        <p class="mt-4 text-[14px] font-bold text-slate-600">{{ item.detail }}</p>
                    </article>
                </div>
            </div>

            <!-- Current Step Aside -->
            <aside class="anim-slide-up rounded-[36px] border border-emerald-100 bg-gradient-to-br from-emerald-50 to-teal-50/50 p-6 shadow-xl shadow-emerald-500/10 lg:p-8">
                <span class="flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-500 text-white shadow-lg shadow-emerald-500/30">
                    <CheckCircle2 class="size-8" />
                </span>
                <h2 class="mt-5 text-2xl font-black text-slate-800">Current step</h2>
                <p class="mt-2 text-[15px] font-bold leading-relaxed text-slate-600">
                    {{ flowState?.message ?? 'Continue your reading path from the dashboard.' }}
                </p>
                <Link
                    :href="flowState?.primary_action_route ?? '/learner/dashboard'"
                    class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-[20px] bg-gradient-to-br from-primary to-blue-600 px-5 py-4 text-lg font-black text-white shadow-lg shadow-primary/20 ring-1 ring-white/20 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-xl active:scale-[0.98]"
                >
                    {{ flowState?.primary_action_label ?? 'Continue' }}
                    <ArrowRight class="size-5" />
                </Link>
            </aside>
        </section>
    </LearnerSimplePageShell>
</template>

<style scoped>
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-slide-up {
    animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

.anim-stagger > * {
    opacity: 0;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.anim-stagger > *:nth-child(1) { animation-delay: 100ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 200ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 400ms; }
</style>

