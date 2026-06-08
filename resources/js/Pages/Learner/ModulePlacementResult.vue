<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Star, BookOpen, Trophy, Target, BarChart3, Sparkles } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';

const props = defineProps({ attempt: Object, decision: Object, module: Object });

const moduleTitle = computed(() => props.module?.title ?? 'Reading at Grade Level');

const evaluatorMessage = computed(() => {
    if (props.module) {
        return `Great job! Your reading path is ${moduleTitle.value}. Tap continue to see it on your dashboard.`;
    }
    return "Wonderful! You're reading at grade level. Tap continue to head to your dashboard.";
});

const metrics = computed(() => [
    { label: 'Task 1 Letters', value: props.attempt?.task_1_score ?? '-' },
    { label: 'Task 2A Rhymes', value: props.attempt?.task_2a_score ?? '-' },
    { label: 'Task 2B Words', value: props.attempt?.task_2b_score ?? '-' },
    { label: 'CRLA Total', value: props.attempt?.crla_total_score ?? '-' },
    { label: 'Passage Accuracy', value: props.attempt?.reading_accuracy ?? '-', suffix: '%' },
    { label: 'Comprehension', value: props.attempt?.comprehension_percentage ?? '-', suffix: '%' },
    { label: 'Reading Score', value: props.attempt?.final_reading_score ?? '-', suffix: '%' },
]);

const topColors = [
    { bg: 'bg-blue-50', text: 'text-blue-600', icon: 'bg-gradient-to-br from-blue-500 to-blue-600' },
    { bg: 'bg-violet-50', text: 'text-violet-600', icon: 'bg-gradient-to-br from-violet-500 to-purple-600' },
    { bg: 'bg-emerald-50', text: 'text-emerald-600', icon: 'bg-gradient-to-br from-emerald-500 to-teal-600' },
    { bg: 'bg-amber-50', text: 'text-amber-600', icon: 'bg-gradient-to-br from-amber-500 to-orange-600' },
];
const bottomColors = [
    { bg: 'bg-cyan-50', text: 'text-cyan-600', icon: 'bg-gradient-to-br from-cyan-500 to-blue-600' },
    { bg: 'bg-pink-50', text: 'text-pink-600', icon: 'bg-gradient-to-br from-pink-500 to-rose-600' },
    { bg: 'bg-indigo-50', text: 'text-indigo-600', icon: 'bg-gradient-to-br from-indigo-500 to-violet-600' },
];
</script>

<template>
    <LearnerLayout :progress="100" diagnostic-step="sentence-reading">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="celebrating"
                presentation="summary"
                :message="evaluatorMessage"
            />
        </template>

        <section class="relative mx-auto grid w-full max-w-[960px] gap-5 pb-8 anim-stagger">
            <!-- Path ready badge -->
            <div class="inline-flex w-full items-center gap-3 rounded-2xl bg-gradient-to-r from-amber-100 to-yellow-100 px-5 py-3 text-[15px] font-black text-amber-700 shadow-md shadow-amber-200/30 ring-1 ring-amber-200/40">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-sm">
                    <Star class="size-5 fill-white" />
                </span>
                Path Ready
                <Sparkles class="ml-auto size-5 text-amber-400" />
            </div>

            <!-- Title -->
            <h1 class="text-center text-3xl font-black leading-tight text-slate-800 xl:text-4xl">
                Your reading path is ready.
            </h1>

            <!-- Module card -->
            <article class="flex items-center gap-4 rounded-[28px] border-[3px] border-primary/10 bg-white px-6 py-5 shadow-xl shadow-primary/10">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20">
                    <Check class="size-7 stroke-[4]" />
                </span>
                <div class="min-w-0">
                    <p class="text-xl font-black text-slate-800">{{ moduleTitle }}</p>
                    <p class="mt-1 text-[14px] font-semibold leading-relaxed text-slate-400">{{ decision.decision_reason }}</p>
                </div>
            </article>

            <!-- Top metrics row -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article
                    v-for="(metric, i) in metrics.slice(0, 4)"
                    :key="metric.label"
                    class="metric-card rounded-[24px] border border-slate-200/60 bg-white p-5 shadow-md shadow-slate-200/20"
                    :style="{ '--delay': `${i * 80}ms` }"
                >
                    <div class="flex items-center gap-2">
                        <span :class="['flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm', topColors[i].icon]">
                            <BarChart3 class="size-3.5" />
                        </span>
                        <p :class="['text-[11px] font-black uppercase tracking-wider', topColors[i].text]">{{ metric.label }}</p>
                    </div>
                    <p class="mt-3 text-3xl font-black leading-none text-slate-800">
                        {{ metric.value }}<span v-if="metric.suffix" class="ml-1 text-xl text-slate-400">{{ metric.suffix }}</span>
                    </p>
                </article>
            </div>

            <!-- Bottom metrics row -->
            <div class="grid gap-3 sm:grid-cols-3">
                <article
                    v-for="(metric, i) in metrics.slice(4)"
                    :key="metric.label"
                    class="metric-card rounded-[24px] border border-slate-200/60 bg-white p-5 shadow-md shadow-slate-200/20"
                    :style="{ '--delay': `${(i + 4) * 80}ms` }"
                >
                    <div class="flex items-center gap-2">
                        <span :class="['flex h-7 w-7 items-center justify-center rounded-lg text-white shadow-sm', bottomColors[i].icon]">
                            <Target class="size-3.5" />
                        </span>
                        <p :class="['text-[11px] font-black uppercase tracking-wider', bottomColors[i].text]">{{ metric.label }}</p>
                    </div>
                    <p class="mt-3 text-3xl font-black leading-none text-slate-800">
                        {{ metric.value }}<span v-if="metric.suffix" class="ml-1 text-xl text-slate-400">{{ metric.suffix }}</span>
                    </p>
                </article>
            </div>

            <!-- Classification cards -->
            <div class="grid gap-3 lg:grid-cols-2">
                <article class="rounded-[24px] border border-slate-200/60 bg-white p-5 shadow-md shadow-slate-200/20">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-blue-600 text-white shadow-sm">
                            <BookOpen class="size-4" />
                        </span>
                        <p class="text-[12px] font-black uppercase tracking-wider text-primary">CRLA Level</p>
                    </div>
                    <p class="mt-3 text-2xl font-black text-slate-800">{{ attempt?.crla_classification }}</p>
                    <p class="mt-3 text-[14px] font-semibold leading-relaxed text-slate-400">{{ decision.crla_meaning }}</p>
                </article>
                <article class="rounded-[24px] border border-slate-200/60 bg-white p-5 shadow-md shadow-slate-200/20">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-purple-600 text-white shadow-sm">
                            <Trophy class="size-4" />
                        </span>
                        <p class="text-[12px] font-black uppercase tracking-wider text-violet-600">Reading Level</p>
                    </div>
                    <p class="mt-3 text-2xl font-black text-slate-800">{{ attempt?.reading_classification }}</p>
                    <p class="mt-3 text-[14px] font-semibold leading-relaxed text-slate-400">{{ decision.reading_meaning }}</p>
                </article>
            </div>

            <!-- Why this path -->
            <article class="rounded-[24px] border border-primary/10 bg-primary/3 p-5 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-blue-600 text-white shadow-sm">
                        <Sparkles class="size-4" />
                    </span>
                    <p class="text-[12px] font-black uppercase tracking-wider text-primary">Why This Path</p>
                </div>
                <p class="mt-3 text-[15px] font-black leading-relaxed text-slate-800">{{ decision.placement_explanation }}</p>
                <p class="mt-3 text-[12px] font-semibold text-slate-400">Rule applied: {{ decision.rule_applied }}</p>
            </article>

            <!-- Continue button -->
            <div class="flex justify-end pt-1">
                <Link href="/learner/dashboard" class="w-full sm:w-auto">
                    <PrimaryButton class="w-full gap-3 sm:w-auto sm:min-w-[300px]">
                        Continue to my Dashboard
                        <ArrowRight class="size-5 stroke-[3] transition-transform duration-200 group-hover:translate-x-1" />
                    </PrimaryButton>
                </Link>
            </div>
        </section>
    </LearnerLayout>
</template>

<style scoped>
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 100ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 200ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(5) { animation-delay: 400ms; }
.anim-stagger > *:nth-child(6) { animation-delay: 500ms; }
.anim-stagger > *:nth-child(7) { animation-delay: 600ms; }
.anim-stagger > *:nth-child(8) { animation-delay: 700ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.metric-card {
    animation: metricPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    animation-delay: var(--delay, 0ms);
}
@keyframes metricPop {
    from { opacity: 0; transform: scale(0.9) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>

