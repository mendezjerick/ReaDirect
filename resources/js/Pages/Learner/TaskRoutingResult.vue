<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Sparkles, CheckCircle2, XCircle } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({
    attempt: Object,
    route: Object,
    itemResponses: {
        type: Array,
        default: () => []
    }
});

const score = computed(() => props.attempt?.task_1_score ?? 0);
const total = 10;

// Determine if we are going to Task 2A or 2B based on backend routing
const requiresTask2A = computed(() => props.route?.requires_task_2a ?? (score.value < 7));
const nextHref = computed(() => requiresTask2A.value ? '/learner/diagnostic/task-2a' : '/learner/diagnostic/task-2b');
const nextLabel = computed(() => requiresTask2A.value ? 'Continue to Task 2A' : 'Continue to Task 2B');
const nextTitle = computed(() => requiresTask2A.value ? 'Task 2A: Rhyme Recognition' : 'Task 2B: Word in Sentence');

const agentMessage = computed(() => {
    if (requiresTask2A.value) {
        return `Good job! You got ${score.value} out of 10. Let's practice some rhymes next!`;
    }
    return `Excellent work! You got ${score.value} out of 10. You did so well we are going to skip ahead to reading sentences!`;
});

// For the breakdown section
const hasItemResults = computed(() => props.itemResponses && props.itemResponses.length > 0);
const correctCount = computed(() => props.itemResponses.filter(r => r.is_correct).length);
const incorrectCount = computed(() => props.itemResponses.filter(r => !r.is_correct).length);

// Calculate SVG stroke dasharray for the circular progress
const radius = 45;
const circumference = 2 * Math.PI * radius;
const scorePercentage = computed(() => (score.value / total) * 100);
const strokeDashoffset = computed(() => circumference - (scorePercentage.value / 100) * circumference);

// Dynamic coloring based on score
const scoreTheme = computed(() => {
    if (score.value >= 7) return { from: '#3B82F6', to: '#10B981', gradient: 'url(#score-gradient-high)' }; // Blue to Green
    if (score.value >= 4) return { from: '#F59E0B', to: '#FDE047', gradient: 'url(#score-gradient-med)' };  // Orange to Yellow
    return { from: '#EF4444', to: '#F97316', gradient: 'url(#score-gradient-low)' };                        // Red to Orange
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

        <!-- Ambient Background Orbs for Premium Feel -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-400/20 rounded-full blur-[100px] anim-pulse-slow"></div>
            <div class="absolute bottom-[10%] right-[-5%] w-[30rem] h-[30rem] bg-indigo-400/20 rounded-full blur-[120px] anim-pulse-slow" style="animation-delay: 2s;"></div>
            <div class="absolute top-[40%] left-[60%] w-80 h-80 bg-emerald-400/10 rounded-full blur-[90px] anim-pulse-slow" style="animation-delay: 4s;"></div>
        </div>

        <div class="relative z-10 w-full max-w-4xl mx-auto px-4 py-6 flex flex-col gap-6 anim-stagger">
            
            <!-- Hero Glass Card: Score -->
            <div class="relative overflow-hidden rounded-[2rem] bg-white/50 backdrop-blur-xl border border-white/80 shadow-[0_8px_32px_rgba(31,38,135,0.07)] p-6 sm:p-10">
                <!-- Inner glow -->
                <div class="absolute inset-0 bg-gradient-to-br from-white/80 to-white/20 pointer-events-none"></div>
                
                <div class="relative flex flex-col sm:flex-row items-center justify-between gap-8">
                    
                    <!-- Left: Circular Progress -->
                    <div class="relative flex shrink-0 items-center justify-center">
                        <svg class="w-40 h-40 transform -rotate-90 drop-shadow-xl" viewBox="0 0 100 100">
                            <!-- Background Track -->
                            <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(226, 232, 240, 0.6)" stroke-width="8" stroke-linecap="round" />
                            <!-- Progress Arc -->
                            <circle 
                                cx="50" cy="50" r="45" fill="none" 
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
                        
                        <!-- Center Text -->
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-4xl font-black text-slate-800 tracking-tighter" style="font-family: 'Inter', system-ui, sans-serif;">{{ score }}</span>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">out of {{ total }}</span>
                        </div>
                    </div>

                    <!-- Right: Details -->
                    <div class="flex-1 text-center sm:text-left space-y-3">
                        <div class="inline-flex items-center justify-center sm:justify-start gap-2 px-3 py-1.5 rounded-full bg-white/60 backdrop-blur-md border border-white/80 shadow-sm text-indigo-700 font-semibold mb-2 mx-auto sm:mx-0">
                            <Sparkles class="size-4" />
                            <span class="text-sm">Task 1 Complete!</span>
                        </div>
                        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-800" style="font-family: 'Inter', system-ui, sans-serif;">
                            Letter Pronunciation
                        </h1>
                        <p class="text-slate-600 font-medium text-lg">
                            You answered {{ score }} out of {{ total }} letters correctly.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Letter Breakdown Section (Glass Card) -->
            <div v-if="hasItemResults" class="relative overflow-hidden rounded-[1.5rem] bg-white/40 backdrop-blur-xl border border-white/70 shadow-lg p-6 sm:p-8">
                <div class="absolute inset-0 bg-gradient-to-b from-white/40 to-transparent pointer-events-none"></div>
                <div class="relative z-10">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-4">Your Letters</p>
                    
                    <div class="flex flex-wrap gap-3 mb-6">
                        <div
                            v-for="(item, idx) in itemResponses"
                            :key="idx"
                            class="relative flex flex-col items-center justify-center w-12 h-14 rounded-xl border border-white/80 shadow-sm backdrop-blur-sm transition-transform hover:-translate-y-1"
                            :class="item.is_correct ? 'bg-emerald-50/70 text-emerald-700 shadow-emerald-500/10' : 'bg-red-50/70 text-red-700 shadow-red-500/10'"
                            :style="`animation-delay: ${100 + idx * 50}ms`"
                        >
                            <span class="font-bold text-lg font-mono">{{ item.letter }}</span>
                            <div 
                                class="absolute -bottom-1 w-2 h-2 rounded-full shadow-sm"
                                :class="item.is_correct ? 'bg-emerald-500' : 'bg-red-500'"
                            ></div>
                        </div>
                    </div>

                    <!-- Summary Pills -->
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-100/50 border border-emerald-200/50 text-emerald-700 font-semibold shadow-sm">
                            <CheckCircle2 class="size-4" />
                            <span class="text-sm">{{ correctCount }} correct</span>
                        </div>
                        <div v-if="incorrectCount > 0" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-100/50 border border-red-200/50 text-red-700 font-semibold shadow-sm">
                            <XCircle class="size-4" />
                            <span class="text-sm">{{ incorrectCount }} to practice</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What's Next Section (Glass Card) -->
            <div class="relative overflow-hidden rounded-[1.5rem] bg-white/40 backdrop-blur-xl border border-white/70 shadow-lg p-6 sm:p-8 flex items-start gap-5">
                <div class="absolute inset-0 bg-gradient-to-r from-white/40 to-transparent pointer-events-none"></div>
                
                <div class="relative z-10 shrink-0 flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
                    <ChevronRight class="size-8 stroke-[2.5]" />
                </div>
                
                <div class="relative z-10 flex-1">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-1">What's Next?</p>
                    <h3 class="text-xl font-extrabold text-slate-800 mb-1" style="font-family: 'Inter', system-ui, sans-serif;">{{ nextTitle }}</h3>
                    <p v-if="requiresTask2A" class="text-slate-600 font-medium">
                        Your score of {{ score }}/10 means we will now do a short rhyming activity.
                    </p>
                    <p v-else class="text-slate-600 font-medium">
                        Great score! We will skip to reading sentences next.
                    </p>
                </div>
            </div>

        </div>

        <BottomActionBar class="bg-white/50 backdrop-blur-2xl border-t border-white/60">
            <Link :href="nextHref" class="w-full sm:w-auto">
                <button class="relative overflow-hidden w-full sm:w-auto group rounded-2xl bg-slate-900 px-8 py-4 text-white shadow-xl hover:shadow-2xl hover:shadow-indigo-500/25 transition-all duration-300 active:scale-95">
                    <!-- Glow effect on hover -->
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity duration-500 bg-[length:200%_auto] animate-gradient"></div>
                    
                    <span class="relative flex items-center justify-center gap-3 font-semibold text-lg tracking-wide">
                        {{ nextLabel }}
                        <ChevronRight class="size-6 transition-transform group-hover:translate-x-1" />
                    </span>
                </button>
            </Link>
        </BottomActionBar>

    </LearnerLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');

/* Animations */
.anim-pulse-slow {
    animation: pulseSlow 10s ease-in-out infinite alternate;
}
@keyframes pulseSlow {
    0% { transform: scale(1) translate(0, 0); opacity: 0.4; }
    100% { transform: scale(1.1) translate(20px, -20px); opacity: 0.7; }
}

.anim-stagger > * {
    animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 50ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 250ms; }

@keyframes slideUpFade {
    0% { opacity: 0; transform: translateY(40px) scale(0.97); filter: blur(4px); }
    100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
}

.animate-gradient {
    animation: gradientShift 3s linear infinite;
}
@keyframes gradientShift {
    0% { background-position: 0% center; }
    100% { background-position: 200% center; }
}
</style>
