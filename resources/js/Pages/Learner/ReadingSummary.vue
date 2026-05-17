<script setup>
import { Link } from '@inertiajs/vue3';
import { Award, BookOpen, Brain, Flag, Map, Target } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ attempt: Object });
</script>

<template>
    <LearnerLayout :progress="94" diagnostic-step="sentence-reading">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="pointing"
                presentation="reading-results"
                message="I used the final reading score to find the reading level."
            />
        </template>

        <div class="relative mx-auto grid w-full max-w-[960px] gap-4 pb-2 xl:gap-5">
            <span class="pointer-events-none absolute -left-8 top-8 hidden text-3xl font-black text-blue-200 xl:block" aria-hidden="true">*</span>
            <span class="pointer-events-none absolute right-8 top-8 hidden text-2xl font-black text-blue-200 sm:block" aria-hidden="true" />
            <span class="pointer-events-none absolute right-2 top-28 hidden text-4xl font-black text-purple-200 xl:block" aria-hidden="true">*</span>

            <div class="relative text-center">
                <span class="absolute left-[20%] top-1/2 hidden h-1 w-4 -translate-y-1/2 rotate-12 rounded-full bg-primary/70 xl:block" aria-hidden="true" />
                <span class="absolute left-[20%] top-[35%] hidden h-1 w-5 -translate-y-1/2 -rotate-12 rounded-full bg-primary/70 xl:block" aria-hidden="true" />
                <span class="absolute right-[20%] top-1/2 hidden h-1 w-4 -translate-y-1/2 -rotate-12 rounded-full bg-primary/70 xl:block" aria-hidden="true" />
                <span class="absolute right-[20%] top-[35%] hidden h-1 w-5 -translate-y-1/2 rotate-12 rounded-full bg-primary/70 xl:block" aria-hidden="true" />
                <h1 class="text-3xl font-black leading-tight text-text xl:text-4xl">Reading check complete.</h1>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="flex min-h-28 items-center gap-4 rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-5">
                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-primary-light text-primary xl:size-18">
                        <BookOpen class="size-8 xl:size-9" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-base font-black uppercase text-primary">Incorrect Words</p>
                        <p class="mt-1 text-4xl font-black leading-none text-text">{{ attempt.incorrect_words }}</p>
                        <p class="mt-2 text-sm font-medium text-muted">from last month</p>
                    </div>
                </article>

                <article class="flex min-h-28 items-center gap-4 rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-5">
                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-success/10 text-success xl:size-18">
                        <Target class="size-9" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-base font-black uppercase text-primary">Accuracy</p>
                        <p class="mt-1 text-4xl font-black leading-none text-text">
                            {{ attempt.reading_accuracy }}<span class="ml-1 text-2xl">%</span>
                        </p>
                        <p class="mt-2 text-sm font-medium text-muted">from last month</p>
                    </div>
                </article>

                <article class="flex min-h-28 items-center gap-4 rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-5">
                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-purple-100 text-purple-500 xl:size-18">
                        <Brain class="size-9" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-base font-black uppercase text-primary">Comprehension</p>
                        <p class="mt-1 text-4xl font-black leading-none text-text">
                            {{ attempt.comprehension_percentage }}<span class="ml-1 text-2xl">%</span>
                        </p>
                        <p class="mt-2 text-sm font-medium text-muted">from last month</p>
                    </div>
                </article>

                <article class="flex min-h-28 items-center gap-4 rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-5">
                    <span class="grid size-16 shrink-0 place-items-center rounded-full bg-accent/20 text-warning xl:size-18">
                        <Award class="size-9" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-base font-black uppercase text-primary">Final Reading Score</p>
                        <p class="mt-1 text-4xl font-black leading-none text-text">{{ attempt.final_reading_score }}</p>
                        <p class="mt-2 text-sm font-medium text-muted">from last month</p>
                    </div>
                </article>
            </div>

            <article class="flex min-h-28 items-center gap-4 rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10 xl:min-h-30 xl:gap-5 xl:px-6 xl:py-5">
                <span class="grid size-16 shrink-0 place-items-center rounded-full bg-blue-50 text-primary xl:size-18">
                    <BookOpen class="size-8 xl:size-9" />
                </span>
                <div class="min-w-0">
                    <p class="text-lg font-black uppercase text-primary">Reading Level</p>
                    <p class="mt-1 break-words text-3xl font-black leading-tight text-text xl:text-4xl">{{ attempt.reading_classification }}</p>
                    <p class="mt-2 text-sm font-medium text-muted">from last month</p>
                </div>
            </article>

            <section class="flex items-center gap-4 rounded-[16px] border border-blue-100 bg-surface px-5 py-4 shadow-lg shadow-primary/10 xl:gap-5 xl:px-6 xl:py-5">
                <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-full border border-blue-100 bg-blue-50 text-primary xl:size-16">
                    <Flag class="size-8 xl:size-9" />
                </span>
                <div class="min-w-0">
                    <p class="text-base font-black uppercase text-primary">Miss Estelle</p>
                    <p class="mt-1 text-base font-medium leading-relaxed text-text xl:text-lg">
                        Accuracy comes from the passage word-error count. Reading level is based on the final reading score.
                    </p>
                </div>
            </section>
        </div>

        <BottomActionBar>
            <Link href="/learner/diagnostic/module-placement">
                <PrimaryButton class="gap-4 rounded-[22px] px-10 shadow-xl shadow-primary/25">
                    <Map class="size-7" />
                    See my path
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
