<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Star } from 'lucide-vue-next';
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

        <section class="relative mx-auto grid w-full max-w-[960px] gap-4 pb-4">
            <div class="inline-flex min-h-11 w-full items-center gap-3 rounded-[16px] bg-accent px-5 py-2 text-base font-black text-text shadow-lg shadow-accent/25">
                <Star class="size-5 fill-text text-text" />
                Path Ready
            </div>

            <h1 class="text-center text-3xl font-black leading-tight text-text xl:text-4xl">Your reading path is ready.</h1>

            <article class="flex items-center gap-4 rounded-[18px] border-2 border-primary bg-surface px-5 py-4 shadow-lg shadow-primary/10">
                <span class="grid size-14 shrink-0 place-items-center rounded-[16px] bg-primary-light text-primary">
                    <Check class="size-8 stroke-[4]" />
                </span>
                <div class="min-w-0">
                    <p class="text-lg font-black text-text">{{ moduleTitle }}</p>
                    <p class="mt-1 text-base font-medium leading-relaxed text-muted">{{ decision.decision_reason }}</p>
                </div>
            </article>

            <div class="grid gap-4 md:grid-cols-4">
                <article
                    v-for="metric in metrics.slice(0, 4)"
                    :key="metric.label"
                    class="rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10"
                >
                    <p class="text-sm font-black uppercase text-muted">{{ metric.label }}</p>
                    <p class="mt-2 text-3xl font-black leading-none text-text">{{ metric.value }}<span v-if="metric.suffix" class="ml-1 text-xl">{{ metric.suffix }}</span></p>
                    <p class="mt-3 text-sm font-medium text-muted">from last month</p>
                </article>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="metric in metrics.slice(4)"
                    :key="metric.label"
                    class="rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10"
                >
                    <p class="text-sm font-black uppercase text-muted">{{ metric.label }}</p>
                    <p class="mt-2 text-3xl font-black leading-none text-text">{{ metric.value }}<span v-if="metric.suffix" class="ml-1 text-xl">{{ metric.suffix }}</span></p>
                    <p class="mt-3 text-sm font-medium text-muted">from last month</p>
                </article>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10">
                    <p class="text-sm font-black uppercase text-primary">CRLA Level</p>
                    <p class="mt-2 text-2xl font-black text-text">{{ attempt?.crla_classification }}</p>
                    <p class="mt-4 text-base font-bold leading-relaxed text-muted">{{ decision.crla_meaning }}</p>
                </article>
                <article class="rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10">
                    <p class="text-sm font-black uppercase text-primary">Reading Level</p>
                    <p class="mt-2 text-2xl font-black text-text">{{ attempt?.reading_classification }}</p>
                    <p class="mt-4 text-base font-bold leading-relaxed text-muted">{{ decision.reading_meaning }}</p>
                </article>
            </div>

            <article class="rounded-[16px] border border-blue-200 bg-blue-50/60 px-5 py-4 shadow-lg shadow-primary/10">
                <p class="text-sm font-black uppercase text-primary">Why This Path</p>
                <p class="mt-3 text-base font-black leading-relaxed text-text">{{ decision.placement_explanation }}</p>
                <p class="mt-4 text-sm font-black text-muted">Rule applied: {{ decision.rule_applied }}</p>
            </article>

            <div class="flex justify-end pt-1">
                <Link href="/learner/dashboard" class="w-full sm:w-auto">
                    <PrimaryButton class="w-full gap-3 rounded-[18px] px-6 text-base shadow-xl shadow-primary/25 sm:w-auto sm:min-w-[300px] sm:px-8">
                        Continue to my Dashboard
                        <ArrowRight class="size-5 stroke-[3]" />
                    </PrimaryButton>
                </Link>
            </div>
        </section>
    </LearnerLayout>
</template>
