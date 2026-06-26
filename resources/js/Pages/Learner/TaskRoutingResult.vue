<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ChevronRight, Sparkles, XCircle } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({
    attempt: Object,
    route: Object,
    itemResponses: {
        type: Array,
        default: () => [],
    },
});

const score = computed(() => props.attempt?.task_1_score ?? 0);
const total = 10;
const requiresTask2A = computed(() => props.route?.requires_task_2a ?? score.value < 7);
const nextHref = computed(() => requiresTask2A.value ? '/learner/diagnostic/task-2a' : '/learner/diagnostic/task-2b');
const nextLabel = computed(() => requiresTask2A.value ? 'Continue to Task 2A' : 'Continue to Task 2B');
const nextTitle = computed(() => requiresTask2A.value ? 'Task 2A: Rhyme Recognition' : 'Task 2B: Word in Sentence');

const agentMessage = computed(() => {
    if (requiresTask2A.value) {
        return `Good job! You got ${score.value} out of 10. Let's practice some rhymes next!`;
    }

    return `Excellent work! You got ${score.value} out of 10. You did so well we are going to skip ahead to reading sentences!`;
});

const hasItemResults = computed(() => Array.isArray(props.itemResponses) && props.itemResponses.length > 0);
const correctCount = computed(() => props.itemResponses.filter((response) => response.is_correct).length);
const incorrectCount = computed(() => props.itemResponses.filter((response) => !response.is_correct).length);

const radius = 45;
const circumference = 2 * Math.PI * radius;
const scorePercentage = computed(() => (score.value / total) * 100);
const strokeDashoffset = computed(() => circumference - (scorePercentage.value / 100) * circumference);

const scoreTheme = computed(() => {
    if (score.value >= 7) {
        return { gradient: 'url(#score-gradient-high)' };
    }

    if (score.value >= 4) {
        return { gradient: 'url(#score-gradient-med)' };
    }

    return { gradient: 'url(#score-gradient-low)' };
});
</script>

<template>
    <LearnerLayout :progress="30" diagnostic-step="task-1">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="encouraging"
                :message="agentMessage"
            />
        </template>

        <div class="relative z-10 mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 anim-stagger">
            <div class="relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/50 p-6 shadow-[0_8px_32px_rgba(31,38,135,0.07)] backdrop-blur-xl sm:p-10">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/80 to-white/20" />

                <div class="relative flex flex-col items-center justify-between gap-8 sm:flex-row">
                    <div class="relative flex shrink-0 items-center justify-center">
                        <svg class="h-40 w-40 -rotate-90 transform drop-shadow-xl" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(226, 232, 240, 0.6)" stroke-width="8" stroke-linecap="round" />
                            <circle
                                cx="50"
                                cy="50"
                                r="45"
                                fill="none"
                                :stroke="scoreTheme.gradient"
                                stroke-width="8"
                                stroke-linecap="round"
                                class="transition-all duration-1000 ease-out"
                                :stroke-dasharray="circumference"
                                :stroke-dashoffset="strokeDashoffset"
                            />
                            <defs>
                                <linearGradient id="score-gradient-high" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#3B82F6" />
                                    <stop offset="100%" stop-color="#10B981" />
                                </linearGradient>
                                <linearGradient id="score-gradient-med" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#F59E0B" />
                                    <stop offset="100%" stop-color="#FDE047" />
                                </linearGradient>
                                <linearGradient id="score-gradient-low" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#EF4444" />
                                    <stop offset="100%" stop-color="#F97316" />
                                </linearGradient>
                            </defs>
                        </svg>

                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-4xl font-black tracking-tight text-slate-800">{{ score }}</span>
                            <span class="mt-1 text-xs font-bold uppercase tracking-widest text-slate-500">out of {{ total }}</span>
                        </div>
                    </div>

                    <div class="flex-1 space-y-3 text-center sm:text-left">
                        <div class="mx-auto mb-2 inline-flex items-center justify-center gap-2 rounded-full border border-white/80 bg-white/60 px-3 py-1.5 font-semibold text-indigo-700 shadow-sm backdrop-blur-md sm:mx-0 sm:justify-start">
                            <Sparkles class="size-4" />
                            <span class="text-sm">Task 1 Complete!</span>
                        </div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-800 sm:text-4xl">
                            Letter Pronunciation
                        </h1>
                        <p class="text-lg font-medium text-slate-600">
                            You answered {{ score }} out of {{ total }} letters correctly.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="hasItemResults" class="relative overflow-hidden rounded-[1.5rem] border border-white/70 bg-white/40 p-6 shadow-lg backdrop-blur-xl sm:p-8">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/40 to-transparent" />
                <div class="relative z-10">
                    <p class="mb-4 text-xs font-bold uppercase tracking-widest text-slate-500">Your Letters</p>

                    <div class="mb-6 flex flex-wrap gap-3">
                        <div
                            v-for="(item, idx) in itemResponses"
                            :key="idx"
                            class="relative flex h-14 w-12 flex-col items-center justify-center rounded-xl border border-white/80 shadow-sm backdrop-blur-sm transition-transform hover:-translate-y-1"
                            :class="item.is_correct ? 'bg-emerald-50/70 text-emerald-700 shadow-emerald-500/10' : 'bg-red-50/70 text-red-700 shadow-red-500/10'"
                            :style="`animation-delay: ${100 + idx * 50}ms`"
                        >
                            <span class="font-mono text-lg font-bold">{{ item.letter }}</span>
                            <div
                                class="absolute -bottom-1 h-2 w-2 rounded-full shadow-sm"
                                :class="item.is_correct ? 'bg-emerald-500' : 'bg-red-500'"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-xl border border-emerald-200/50 bg-emerald-100/50 px-4 py-2 font-semibold text-emerald-700 shadow-sm">
                            <CheckCircle2 class="size-4" />
                            <span class="text-sm">{{ correctCount }} correct</span>
                        </div>
                        <div v-if="incorrectCount > 0" class="inline-flex items-center gap-2 rounded-xl border border-red-200/50 bg-red-100/50 px-4 py-2 font-semibold text-red-700 shadow-sm">
                            <XCircle class="size-4" />
                            <span class="text-sm">{{ incorrectCount }} to practice</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative flex items-start gap-5 overflow-hidden rounded-[1.5rem] border border-white/70 bg-white/40 p-6 shadow-lg backdrop-blur-xl sm:p-8">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/40 to-transparent" />

                <div class="relative z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
                    <ChevronRight class="size-8 stroke-[2.5]" />
                </div>

                <div class="relative z-10 flex-1">
                    <p class="mb-1 text-xs font-bold uppercase tracking-widest text-slate-500">What's Next?</p>
                    <h3 class="mb-1 text-xl font-extrabold text-slate-800">{{ nextTitle }}</h3>
                    <p v-if="requiresTask2A" class="font-medium text-slate-600">
                        Your score of {{ score }}/10 means we will now do a short rhyming activity.
                    </p>
                    <p v-else class="font-medium text-slate-600">
                        Great score! We will skip to reading sentences next.
                    </p>
                </div>
            </div>
        </div>

        <BottomActionBar class="border-t border-white/60 bg-white/50 backdrop-blur-2xl">
            <Link :href="nextHref" class="w-full sm:w-auto">
                <button class="group relative w-full overflow-hidden rounded-2xl bg-slate-900 px-8 py-4 text-white shadow-xl transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-500/25 active:scale-95 sm:w-auto">
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 bg-[length:200%_auto] opacity-0 transition-opacity duration-500 group-hover:opacity-100 animate-gradient" />

                    <span class="relative flex items-center justify-center gap-3 text-lg font-semibold tracking-wide">
                        {{ nextLabel }}
                        <ChevronRight class="size-6 transition-transform group-hover:translate-x-1" />
                    </span>
                </button>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
.anim-stagger > * {
    animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.anim-stagger > *:nth-child(1) { animation-delay: 50ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 250ms; }

@keyframes slideUpFade {
    0% {
        opacity: 0;
        transform: translateY(40px) scale(0.97);
        filter: blur(4px);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
    }
}

.animate-gradient {
    animation: gradientShift 3s linear infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% center; }
    100% { background-position: 200% center; }
}
</style>
