<script setup>
import { Link } from '@inertiajs/vue3';
import { Award, BookOpen, Brain, Flag, Map, Target, ArrowRightIcon } from 'lucide-vue-next';
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

        <div class="relative mx-auto grid w-full max-w-[960px] gap-5 pb-2 xl:gap-6">
            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 top-40 h-40 w-40 rounded-full bg-blue-500/5 blur-3xl" aria-hidden="true" />

            <!-- Sparkle decorations -->
            <span class="pointer-events-none absolute -left-6 top-10 hidden text-4xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute right-4 top-6 hidden text-3xl font-black text-primary/5 sm:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute right-0 top-32 hidden text-4xl font-black text-primary/5 xl:block" aria-hidden="true">✦</span>

            <!-- Title -->
            <div class="anim-fade-down relative text-center">
                <h1 class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-3xl font-black leading-tight text-transparent xl:text-4xl">
                    Reading check complete.
                </h1>
            </div>

            <!-- Metric cards grid -->
            <div class="anim-stagger grid gap-4 lg:grid-cols-2">
                <!-- Incorrect Words -->
                <article class="anim-card flex min-h-28 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg shadow-blue-500/20 ring-1 ring-white/20 xl:size-16">
                        <BookOpen class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">Incorrect Words</p>
                        <p class="mt-1.5 text-4xl font-black leading-none text-slate-800">{{ attempt.incorrect_words }}</p>
                        <p class="mt-2 text-[13px] font-semibold text-slate-400">from last month</p>
                    </div>
                </article>

                <!-- Accuracy -->
                <article class="anim-card flex min-h-28 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-lg shadow-emerald-500/20 ring-1 ring-white/20 xl:size-16">
                        <Target class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">Accuracy</p>
                        <p class="mt-1.5 text-4xl font-black leading-none text-slate-800">
                            {{ attempt.reading_accuracy }}<span class="ml-1 text-2xl text-slate-500">%</span>
                        </p>
                        <div class="mt-2.5">
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                                <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-green-500 shadow-sm shadow-emerald-500/30 transition-all duration-500 ease-out" :style="{ width: `${Math.min(attempt.reading_accuracy, 100)}%` }" />
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Comprehension -->
                <article class="anim-card flex min-h-28 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-violet-600 text-white shadow-lg shadow-violet-500/20 ring-1 ring-white/20 xl:size-16">
                        <Brain class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">Comprehension</p>
                        <p class="mt-1.5 text-4xl font-black leading-none text-slate-800">
                            {{ attempt.comprehension_percentage }}<span class="ml-1 text-2xl text-slate-500">%</span>
                        </p>
                        <div class="mt-2.5">
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                                <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-purple-500 shadow-sm shadow-violet-500/30 transition-all duration-500 ease-out" :style="{ width: `${Math.min(attempt.comprehension_percentage, 100)}%` }" />
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Final Reading Score -->
                <article class="anim-card flex min-h-28 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-300 to-amber-500 text-white shadow-lg shadow-amber-500/20 ring-1 ring-white/20 xl:size-16">
                        <Award class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">Final Reading Score</p>
                        <p class="mt-1.5 text-4xl font-black leading-none text-slate-800">{{ attempt.final_reading_score }}</p>
                        <p class="mt-2 text-[13px] font-semibold text-slate-400">from last month</p>
                    </div>
                </article>
            </div>

            <!-- Reading Level - hero card -->
            <article class="anim-slide-up flex min-h-28 items-center gap-5 rounded-[36px] border border-slate-200/80 bg-white px-6 py-6 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-6 xl:px-8 xl:py-7">
                <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg shadow-blue-500/20 ring-1 ring-white/20 xl:size-18">
                    <BookOpen class="size-8 xl:size-9" />
                </span>
                <div class="min-w-0">
                    <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">Reading Level</p>
                    <p class="mt-1.5 break-words text-3xl font-black leading-tight text-slate-800 xl:text-4xl">{{ attempt.reading_classification }}</p>
                    <p class="mt-2 text-[13px] font-semibold text-slate-400">from last month</p>
                </div>
            </article>

            <!-- Agent explanation card -->
            <section class="anim-slide-up flex items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:gap-5 xl:px-6 xl:py-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20 xl:size-14">
                    <Flag class="size-6 xl:size-7" />
                </span>
                <div class="min-w-0">
                    <p class="text-[16px] font-black text-slate-800">Miss Estelle</p>
                    <p class="mt-1 text-[14px] font-semibold leading-relaxed text-slate-500">
                        Accuracy comes from the passage word-error count. Reading level is based on the final reading score.
                    </p>
                </div>
            </section>
        </div>

        <BottomActionBar>
            <Link href="/learner/diagnostic/module-placement" class="w-full sm:w-auto">
                <PrimaryButton class="w-full gap-3 rounded-full px-6 py-3.5 text-lg shadow-xl shadow-primary/25 sm:w-auto sm:px-10 sm:py-4 sm:text-xl">
                    See my path
                    <ArrowRightIcon class="size-5 sm:size-6" />
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* Card spring entrance */
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Header fade down */
.anim-fade-down {
    animation: fadeDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Panel slide up */
.anim-slide-up {
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Staggered children */
.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 0ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 450ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

