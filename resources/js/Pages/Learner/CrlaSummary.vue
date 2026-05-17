<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Clock3, Music, Star, WholeWord } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';

defineProps({
    attempt: Object,
    placementPreview: Object,
    taskTwoBReview: Object,
});

const accuracyTone = (percentage) => {
    if (percentage >= 90) return 'text-success bg-success/10';
    if (percentage >= 75) return 'text-primary bg-primary-light';
    if (percentage >= 60) return 'text-warning bg-accent/20';
    return 'text-danger bg-danger/10';
};
</script>

<template>
    <LearnerLayout :progress="65" diagnostic-step="task-2b">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="celebrating"
                presentation="summary"
                message="The CRLA tasks are complete. Review each score, then we will read a short passage to finish placement."
            />
        </template>

        <div class="relative mx-auto grid w-full max-w-[1120px] gap-4 overflow-hidden pb-1 sm:gap-5">
            <span class="pointer-events-none absolute right-8 top-3 hidden text-2xl font-black text-blue-200 sm:block" aria-hidden="true">*</span>
            <span class="pointer-events-none absolute right-0 top-12 hidden text-5xl font-black text-accent sm:block" aria-hidden="true">*</span>

            <div class="inline-flex min-h-11 w-full items-center gap-3 rounded-[16px] bg-accent px-4 py-2 text-base font-black text-text shadow-lg shadow-accent/25 sm:h-12 sm:rounded-[18px] sm:px-5 sm:text-lg">
                <Star class="size-5 fill-text text-text" />
                CRLA Complete
            </div>

            <div class="relative text-center">
                <span class="absolute left-[18%] top-1/2 hidden h-1 w-4 -translate-y-1/2 rotate-12 rounded-full bg-primary/50 xl:block" aria-hidden="true" />
                <span class="absolute left-[18%] top-[35%] hidden h-1 w-5 -translate-y-1/2 -rotate-12 rounded-full bg-primary/50 xl:block" aria-hidden="true" />
                <span class="absolute right-[18%] top-1/2 hidden h-1 w-4 -translate-y-1/2 -rotate-12 rounded-full bg-primary/50 xl:block" aria-hidden="true" />
                <span class="absolute right-[18%] top-[35%] hidden h-1 w-5 -translate-y-1/2 rotate-12 rounded-full bg-primary/50 xl:block" aria-hidden="true" />
                <h1 class="text-3xl font-black leading-tight text-text sm:text-4xl xl:text-5xl">Your CRLA score is ready.</h1>
            </div>

            <div class="grid gap-3 sm:grid-cols-3 sm:gap-4 xl:gap-5">
                <article class="flex min-h-24 items-center gap-4 rounded-[18px] border border-blue-100 bg-surface px-4 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-6 xl:px-8 xl:py-6">
                    <span class="grid size-14 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-20">
                        <WholeWord class="size-7 xl:size-10" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase text-muted xl:text-base">Task 1 Letters</p>
                        <p class="mt-1 text-3xl font-black leading-none text-text xl:text-4xl">{{ attempt.task_1_score }}</p>
                        <p class="mt-1 text-xs font-medium text-muted xl:mt-2 xl:text-base">from last month</p>
                    </div>
                </article>

                <article class="flex min-h-24 items-center gap-4 rounded-[18px] border border-blue-100 bg-surface px-4 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-6 xl:px-8 xl:py-6">
                    <span class="grid size-14 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-20">
                        <Music class="size-7 xl:size-10" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase text-muted xl:text-base">Task 2A Rhymes</p>
                        <p class="mt-1 text-3xl font-black leading-none text-text xl:text-4xl">{{ attempt.task_2a_score }}</p>
                        <p class="mt-1 text-xs font-medium text-muted xl:mt-2 xl:text-base">from last month</p>
                    </div>
                </article>

                <article class="flex min-h-24 items-center gap-4 rounded-[18px] border border-blue-100 bg-surface px-4 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-6 xl:px-8 xl:py-6">
                    <span class="grid size-14 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-20">
                        <BookOpen class="size-7 xl:size-10" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase text-muted xl:text-base">Task 2B Words</p>
                        <p class="mt-1 text-3xl font-black leading-none text-text xl:text-4xl">{{ attempt.task_2b_score }}</p>
                        <p class="mt-1 text-xs font-medium text-muted xl:mt-2 xl:text-base">from last month</p>
                    </div>
                </article>
            </div>

            <section class="rounded-[20px] border border-blue-100 bg-surface px-4 py-5 shadow-lg shadow-primary/10 sm:px-6 xl:px-8 xl:py-6">
                <div class="grid gap-5 xl:grid-cols-[220px_1px_1fr] xl:items-center">
                    <div>
                        <p class="text-base font-black uppercase text-primary sm:text-lg">CRLA Total</p>
                        <p class="mt-2 text-5xl font-black leading-none text-primary xl:mt-3 xl:text-6xl">{{ attempt.crla_total_score }}/30</p>
                        <p class="mt-2 text-xl font-black text-text xl:mt-3 xl:text-2xl">{{ attempt.crla_classification }}</p>
                    </div>
                    <div class="hidden h-32 bg-border xl:block" aria-hidden="true" />
                    <div class="grid gap-4 xl:gap-5">
                        <div class="flex items-start gap-3 rounded-[16px] border border-blue-100 bg-blue-50 px-4 py-4 text-sm font-black text-text shadow-sm sm:items-center xl:gap-4 xl:px-5 xl:text-base">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-success text-white">
                                <Star class="size-5 fill-white text-white" />
                            </span>
                            {{ placementPreview?.crla_meaning }}
                        </div>
                        <p class="text-base font-medium leading-relaxed text-text sm:text-lg xl:text-xl">
                            {{ placementPreview?.decision_reason }}
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="taskTwoBReview" class="rounded-[20px] border border-blue-100 bg-surface px-4 py-5 shadow-lg shadow-primary/10 sm:px-6 xl:px-8">
                <div class="grid gap-5 xl:grid-cols-[1fr_1px_1fr] xl:items-center">
                    <div class="flex items-center gap-4 xl:gap-5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-12">
                            <BookOpen class="size-6 xl:size-7" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-lg font-black uppercase text-primary xl:text-xl">Task 2B Word Results</p>
                            <p class="mt-2 text-sm font-black text-muted xl:mt-3 xl:text-base">Average accuracy</p>
                            <p class="text-3xl font-black text-primary xl:text-4xl">{{ taskTwoBReview.average_accuracy_percentage }}%</p>
                        </div>
                    </div>
                    <div class="hidden h-16 bg-border xl:block" aria-hidden="true" />
                    <div class="flex items-center gap-4 xl:gap-5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-12">
                            <Clock3 class="size-6 xl:size-7" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-muted xl:text-base">What we noticed</p>
                            <p class="mt-1 text-xl font-black capitalize text-text xl:text-2xl">{{ taskTwoBReview.feedback_label }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="taskTwoBReview.items?.length" class="mt-5 grid gap-3 2xl:grid-cols-2">
                    <article
                        v-for="item in taskTwoBReview.items"
                        :key="item.item_number"
                        class="grid gap-3 rounded-[16px] border border-blue-100 bg-background px-4 py-4 sm:grid-cols-[1fr_auto] sm:items-center xl:px-5"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-black uppercase text-primary xl:text-base">Task 2B Item {{ item.item_number }}</p>
                            <p class="mt-1 break-words text-lg font-black text-text xl:text-xl">{{ item.prompt }}</p>
                            <p class="mt-1 text-sm font-medium italic capitalize text-muted xl:text-base">{{ item.feedback_label }}</p>
                        </div>
                        <p class="justify-self-start rounded-[16px] px-4 py-2 text-2xl font-black sm:justify-self-end xl:px-5 xl:py-3 xl:text-3xl" :class="accuracyTone(item.accuracy_percentage)">
                            {{ item.accuracy_percentage }}%
                        </p>
                    </article>
                </div>
            </section>

            <div class="flex justify-end pb-3">
                <Link href="/learner/diagnostic/reading-intro" class="w-full sm:w-auto">
                    <PrimaryButton class="w-full gap-3 rounded-[18px] px-5 text-base shadow-xl shadow-primary/25 sm:w-auto sm:min-w-[320px] sm:gap-4 sm:rounded-[22px] sm:px-9 sm:text-lg">
                        Continue to Passage Reading
                        <ArrowRight class="size-5 stroke-[3] sm:size-6" />
                    </PrimaryButton>
                </Link>
            </div>
        </div>

    </LearnerLayout>
</template>
