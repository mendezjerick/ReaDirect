<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Clock3, Music, Star, WholeWord } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

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

        <div class="relative mx-auto grid w-full max-w-[1120px] gap-5 overflow-hidden pb-1 sm:gap-6">
            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 top-52 h-40 w-40 rounded-full bg-blue-500/5 blur-3xl" aria-hidden="true" />

            <!-- Sparkle decorations -->
            <span class="pointer-events-none absolute right-6 top-2 hidden text-3xl font-black text-primary/5 sm:block" aria-hidden="true">✦</span>
            <span class="pointer-events-none absolute right-0 top-14 hidden text-4xl font-black text-primary/5 sm:block" aria-hidden="true">✦</span>

            <!-- Status badge -->
            <div class="anim-fade-down inline-flex min-h-11 w-full items-center gap-3 rounded-full bg-gradient-to-r from-amber-100 to-yellow-100 px-5 py-2.5 text-[15px] font-black text-amber-700 ring-1 ring-amber-200/50 sm:h-12 sm:text-base">
                <Star class="size-5 fill-amber-500 text-amber-500" />
                CRLA Complete
            </div>

            <!-- Title -->
            <div class="anim-fade-down relative text-center">
                <h1 class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-3xl font-black leading-tight text-transparent sm:text-4xl xl:text-5xl">
                    Your CRLA score is ready.
                </h1>
            </div>

            <!-- Task score cards -->
            <div class="anim-stagger grid gap-4 sm:grid-cols-3 sm:gap-5">
                <!-- Task 1 Letters -->
                <article class="flex min-h-24 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg shadow-blue-500/20 ring-1 ring-white/20 xl:size-16">
                        <WholeWord class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[13px] font-black uppercase tracking-widest text-slate-400 xl:text-[14px]">Task 1 Letters</p>
                        <p class="mt-1.5 text-3xl font-black leading-none text-slate-800 xl:text-4xl">{{ attempt.task_1_score }}</p>
                        <p class="mt-1.5 text-[12px] font-semibold text-slate-400 xl:mt-2 xl:text-[13px]">from last month</p>
                    </div>
                </article>

                <!-- Task 2A Rhymes -->
                <article class="flex min-h-24 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 to-violet-600 text-white shadow-lg shadow-violet-500/20 ring-1 ring-white/20 xl:size-16">
                        <Music class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[13px] font-black uppercase tracking-widest text-slate-400 xl:text-[14px]">Task 2A Rhymes</p>
                        <p class="mt-1.5 text-3xl font-black leading-none text-slate-800 xl:text-4xl">{{ attempt.task_2a_score }}</p>
                        <p class="mt-1.5 text-[12px] font-semibold text-slate-400 xl:mt-2 xl:text-[13px]">from last month</p>
                    </div>
                </article>

                <!-- Task 2B Words -->
                <article class="flex min-h-24 items-center gap-4 rounded-[28px] border border-slate-200/80 bg-white px-5 py-5 shadow-xl shadow-slate-200/30 xl:min-h-32 xl:gap-5 xl:px-6 xl:py-6">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-lg shadow-emerald-500/20 ring-1 ring-white/20 xl:size-16">
                        <BookOpen class="size-7 xl:size-8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[13px] font-black uppercase tracking-widest text-slate-400 xl:text-[14px]">Task 2B Words</p>
                        <p class="mt-1.5 text-3xl font-black leading-none text-slate-800 xl:text-4xl">{{ attempt.task_2b_score }}</p>
                        <p class="mt-1.5 text-[12px] font-semibold text-slate-400 xl:mt-2 xl:text-[13px]">from last month</p>
                    </div>
                </article>
            </div>

            <!-- CRLA Total - hero card -->
            <section class="anim-slide-up rounded-[36px] border border-slate-200/80 bg-white px-6 py-6 shadow-xl shadow-slate-200/30 sm:px-8 xl:px-10 xl:py-8">
                <div class="grid gap-6 xl:grid-cols-[240px_1px_1fr] xl:items-center">
                    <div class="text-center xl:text-left">
                        <p class="text-[14px] font-black uppercase tracking-widest text-slate-400">CRLA Total</p>
                        <p class="mt-3 text-5xl font-black leading-none xl:mt-4 xl:text-6xl">
                            <span class="bg-gradient-to-br from-primary to-blue-600 bg-clip-text text-transparent">{{ attempt.crla_total_score }}</span>
                            <span class="text-slate-300">/30</span>
                        </p>
                        <p class="mt-3 text-xl font-black text-slate-800 xl:mt-4 xl:text-2xl">{{ attempt.crla_classification }}</p>
                    </div>
                    <div class="hidden h-32 rounded-full bg-slate-200/60 xl:block" aria-hidden="true" />
                    <div class="grid gap-4 xl:gap-5">
                        <div class="flex items-start gap-3 rounded-[20px] border border-slate-200/60 bg-slate-50/50 px-5 py-4 text-[14px] font-black text-slate-800 shadow-sm sm:items-center xl:gap-4 xl:text-[15px]">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-md shadow-emerald-500/20 ring-1 ring-white/20">
                                <Star class="size-5 fill-white text-white" />
                            </span>
                            {{ placementPreview?.crla_meaning }}
                        </div>
                        <p class="text-[15px] font-semibold leading-relaxed text-slate-500 sm:text-base xl:text-lg">
                            {{ placementPreview?.decision_reason }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Task 2B Review section -->
            <section v-if="taskTwoBReview" class="anim-slide-up rounded-[28px] border border-slate-200/80 bg-white px-5 py-6 shadow-xl shadow-slate-200/30 sm:px-6 xl:px-8">
                <div class="grid gap-6 xl:grid-cols-[1fr_1px_1fr] xl:items-center">
                    <div class="flex items-center gap-4 xl:gap-5">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg shadow-blue-500/20 ring-1 ring-white/20 xl:size-14">
                            <BookOpen class="size-6 xl:size-7" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[16px] font-black text-slate-800 xl:text-lg">Task 2B Word Results</p>
                            <p class="mt-1.5 text-[13px] font-black uppercase tracking-widest text-slate-400">Average accuracy</p>
                            <p class="text-3xl font-black xl:text-4xl">
                                <span class="bg-gradient-to-br from-primary to-blue-600 bg-clip-text text-transparent">{{ taskTwoBReview.average_accuracy_percentage }}%</span>
                            </p>
                        </div>
                    </div>
                    <div class="hidden h-16 rounded-full bg-slate-200/60 xl:block" aria-hidden="true" />
                    <div class="flex items-center gap-4 xl:gap-5">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-300 to-amber-500 text-white shadow-lg shadow-amber-500/20 ring-1 ring-white/20 xl:size-14">
                            <Clock3 class="size-6 xl:size-7" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-widest text-slate-400">What we noticed</p>
                            <p class="mt-1.5 text-xl font-black capitalize text-slate-800 xl:text-2xl">{{ taskTwoBReview.feedback_label }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="taskTwoBReview.items?.length" class="anim-stagger mt-6 grid gap-3 2xl:grid-cols-2">
                    <article
                        v-for="item in taskTwoBReview.items"
                        :key="item.item_number"
                        class="grid gap-3 rounded-[24px] border border-slate-200/60 bg-slate-50/50 px-5 py-4 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center"
                    >
                        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-widest text-primary">Task 2B Item {{ item.item_number }}</p>
                            <p class="mt-1 break-words text-lg font-black text-slate-800 xl:text-xl">{{ item.prompt }}</p>
                            <p class="mt-1 text-[13px] font-semibold italic capitalize text-slate-400 xl:text-[14px]">{{ item.feedback_label }}</p>
                        </div>
                        <p class="justify-self-start rounded-[20px] px-4 py-2 text-2xl font-black sm:justify-self-end xl:px-5 xl:py-3 xl:text-3xl" :class="accuracyTone(item.accuracy_percentage)">
                            {{ item.accuracy_percentage }}%
                        </p>
                    </article>
                </div>
            </section>

        </div>

        <BottomActionBar>
            <Link href="/learner/diagnostic/reading-intro" class="w-full sm:w-auto">
                <PrimaryButton class="w-full gap-3 rounded-[22px] px-5 text-base shadow-xl shadow-primary/25 sm:w-auto sm:min-w-[320px] sm:gap-4 sm:px-9 sm:text-lg">
                    Continue to Passage Reading
                    <ArrowRight class="size-5 stroke-[3] sm:size-6" />
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

/* Content pop */
.anim-pop {
    animation: contentPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes contentPop {
    from { opacity: 0; transform: scale(0.7); }
    to { opacity: 1; transform: scale(1); }
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
