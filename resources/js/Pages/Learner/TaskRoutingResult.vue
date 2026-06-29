<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ChevronRight, Sparkles, XCircle } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentVideoPlayer from '../../Components/Agents/AgentVideoPlayer.vue';
import AgentSpeakerTTS from '../../Components/Agents/AgentSpeakerTTS.vue';

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

const evaluatorMessage = computed(() => {
    return 'You finished the first reading task. Your score helps us decide which reading activity should come next.';
});

const isSpeaking = ref(false);
const ttsKey = ref(0);
const isMuted = ref(false);
const voicePayload = ref(null);
const voiceLoading = ref(false);
const voiceRequestId = ref(0);

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const loadNaturalVoice = async () => {
    const text = evaluatorMessage.value?.trim();
    const requestId = voiceRequestId.value + 1;
    voiceRequestId.value = requestId;
    voicePayload.value = null;

    if (!text || typeof window === 'undefined') return;

    voiceLoading.value = true;
    try {
        const response = await fetch('/agent-voice/synthesize', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ agent: 'evaluator', text }),
        });

        if (requestId !== voiceRequestId.value) return;
        voicePayload.value = response.ok ? await response.json() : { audio_url: null };
    } catch {
        if (requestId !== voiceRequestId.value) return;
        voicePayload.value = { audio_url: null };
    } finally {
        if (requestId === voiceRequestId.value) voiceLoading.value = false;
    }
};

onMounted(() => loadNaturalVoice());

const replayVoice = () => {
    isMuted.value = false;
    ttsKey.value++;
};

const hasItemResults = computed(() => Array.isArray(props.itemResponses) && props.itemResponses.length > 0);
const correctCount = computed(() => props.itemResponses.filter((response) => response.is_correct).length);
const incorrectCount = computed(() => props.itemResponses.filter((response) => !response.is_correct).length);

const radius = 45;
const circumference = 2 * Math.PI * radius;
const scorePercentage = computed(() => (score.value / total) * 100);
const strokeDashoffset = computed(() => circumference - (scorePercentage.value / 100) * circumference);

const scoreTheme = computed(() => {
    if (score.value >= 7) {
        return { gradient: 'url(#score-gradient-high)', color: 'text-emerald-500' };
    }
    if (score.value >= 4) {
        return { gradient: 'url(#score-gradient-med)', color: 'text-amber-500' };
    }
    return { gradient: 'url(#score-gradient-low)', color: 'text-red-500' };
});
</script>

<template>
    <LearnerLayout :progress="30" diagnostic-step="task-1" class="rd-full-layout">
        
        <!-- Fixed full-bleed background layer -->
        <div class="fixed inset-0 z-[1] pointer-events-none bg-gradient-to-b from-[#e6f2ff] to-[#f4f9ff]"></div>

        <!-- Main Content Container -->
        <div class="rd-celebration-bg relative z-[2] w-full px-4 pb-12 pt-6 anim-stagger">
            
            <!-- Confetti dots -->
            <div class="absolute left-[20%] top-[30%] h-3 w-3 rounded-full bg-red-400 opacity-70"></div>
            <div class="absolute right-[25%] top-[20%] h-2 w-2 rounded-full bg-blue-400 opacity-70"></div>
            <div class="absolute left-[30%] top-[60%] h-2.5 w-2.5 rounded-full bg-yellow-400 opacity-70"></div>
            <div class="absolute right-[15%] top-[50%] h-3 w-3 rounded-full bg-green-400 opacity-70"></div>
            <div class="absolute left-[10%] top-[40%] h-2 w-2 rounded-full bg-purple-400 opacity-70"></div>
            
            <div class="relative z-10 mx-auto max-w-[1100px]">
                
                <!-- Mascot Area -->
                <div class="mb-6 flex flex-col items-center justify-center">
                    <AgentSpeakerTTS
                        v-if="!voiceLoading"
                        :key="ttsKey"
                        agent-type="evaluator"
                        :message="evaluatorMessage"
                        :mute="isMuted"
                        :audio-url="voicePayload?.audio_url"
                        @speaking-start="isSpeaking = true"
                        @speaking-end="isSpeaking = false"
                    />

                    <div class="relative h-36 w-36 sm:h-40 sm:w-40">
                        <div class="absolute inset-0 scale-125 rounded-full bg-white/60 blur-xl"></div>
                        <AgentVideoPlayer agent="Estelle" agent-type="evaluator" :action="isSpeaking ? 'speaking' : 'celebrating'" class="relative z-10 h-full w-full object-contain" />
                        <Sparkles class="absolute -right-4 top-10 size-6 text-yellow-400 anim-twinkle" />
                        <Sparkles class="absolute -left-6 bottom-16 size-5 text-yellow-400 anim-twinkle-delay" />
                    </div>
                    
                    <div class="relative z-20 -mt-6 flex justify-center">
                        <div class="rounded-lg bg-[#F58549] px-6 py-2 text-sm font-black tracking-wider text-white shadow-lg shadow-orange-500/30 ring-4 ring-white">
                            MISS ESTELLE
                        </div>
                    </div>
                    
                    <div class="mt-4 flex justify-center gap-3">
                        <button class="rounded-full bg-white/90 px-4 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-500 shadow-sm ring-1 ring-slate-200 transition-colors hover:bg-slate-50 hover:text-slate-700">
                            {{ isSpeaking ? 'Speaking' : 'Celebrating' }}
                        </button>
                        <button @click="replayVoice" class="rounded-full bg-white/90 px-4 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-500 shadow-sm ring-1 ring-slate-200 transition-colors hover:bg-slate-50 hover:text-slate-700 hover:text-primary">
                            Replay
                        </button>
                    </div>
                </div>

                <!-- Hero Title -->
                <div class="mb-6 text-center">
                    <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1.5 text-[13px] font-black text-blue-600 shadow-sm">
                        <Sparkles class="size-4 stroke-[3]" /> Task 1 Complete!
                    </div>
                    <h1 class="text-3xl font-black text-[#1e293b] sm:text-4xl">Letter Pronunciation</h1>
                    <p class="mt-2 text-base font-bold text-slate-500 sm:text-lg">You answered {{ score }} out of {{ total }} letters correctly.</p>
                </div>

                <!-- Split Content Layout -->
                <div class="grid gap-6 lg:grid-cols-[1fr_400px] xl:grid-cols-[1fr_450px]">
                    
                    <!-- Left Column -->
                    <div class="flex flex-col gap-6">
                        
                        <!-- Score Card -->
                        <div class="flex flex-col items-center justify-between gap-6 rounded-[24px] border-2 border-slate-100 bg-white p-6 shadow-sm sm:flex-row sm:p-8">
                            <div class="relative flex shrink-0 items-center justify-center">
                                <svg class="h-32 w-32 -rotate-90 transform drop-shadow-md sm:h-40 sm:w-40" viewBox="0 0 100 100">
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
                                    <span class="text-3xl font-black tracking-tight text-slate-800 sm:text-4xl">{{ score }}</span>
                                    <span class="mt-1 text-[10px] font-bold uppercase tracking-widest text-slate-500 sm:text-xs">out of {{ total }}</span>
                                </div>
                            </div>
                            
                            <div class="flex-1 space-y-2 text-center sm:text-left">
                                <h3 class="text-xl font-black text-slate-800">Your Score</h3>
                                <p class="text-sm font-medium text-slate-500">You did great on the Letter Pronunciation task!</p>
                            </div>
                        </div>

                        <!-- Your Letters -->
                        <div v-if="hasItemResults" class="rounded-[24px] border-2 border-slate-100 bg-white p-6 shadow-sm sm:p-8">
                            <p class="mb-4 text-[11px] font-black uppercase tracking-wider text-slate-400">Your Letters</p>

                            <div class="mb-6 flex flex-wrap gap-3">
                                <div
                                    v-for="(item, idx) in itemResponses"
                                    :key="idx"
                                    class="relative flex h-14 w-12 flex-col items-center justify-center rounded-xl border border-slate-100 shadow-sm transition-transform hover:-translate-y-1"
                                    :class="item.is_correct ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                                >
                                    <span class="font-mono text-lg font-bold">{{ item.letter }}</span>
                                    <div
                                        class="absolute -bottom-1 h-2 w-2 rounded-full shadow-sm"
                                        :class="item.is_correct ? 'bg-emerald-500' : 'bg-red-500'"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <div class="inline-flex items-center gap-2 rounded-xl bg-emerald-100 px-3 py-1.5 font-bold text-emerald-700">
                                    <CheckCircle2 class="size-4 stroke-[3]" />
                                    <span class="text-xs">{{ correctCount }} correct</span>
                                </div>
                                <div v-if="incorrectCount > 0" class="inline-flex items-center gap-2 rounded-xl bg-red-100 px-3 py-1.5 font-bold text-red-700">
                                    <XCircle class="size-4 stroke-[3]" />
                                    <span class="text-xs">{{ incorrectCount }} to practice</span>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div class="mt-2 flex w-full">
                            <Link :href="nextHref" class="w-full">
                                <button class="rd-cta-pill w-full">
                                    {{ nextLabel }}
                                    <ChevronRight class="ml-2 size-6 stroke-[3]" />
                                </button>
                            </Link>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="flex flex-col gap-4">
                        <!-- What's Next? -->
                        <div class="flex flex-col items-start rounded-[24px] border-2 border-blue-200 bg-[#f4f9ff] p-5 shadow-sm">
                            <div class="mb-3 inline-flex items-center gap-2 rounded-xl bg-blue-100 px-2.5 py-1.5">
                                <ChevronRight class="size-4 text-blue-600 stroke-[3]" />
                                <span class="text-[11px] font-black uppercase tracking-wider text-blue-600">What's Next?</span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-800">{{ nextTitle }}</h3>
                            <p v-if="requiresTask2A" class="mt-2 text-sm font-bold leading-relaxed text-slate-600">
                                Your score of {{ score }}/10 means we will now do a short rhyming activity.
                            </p>
                            <p v-else class="mt-2 text-sm font-bold leading-relaxed text-slate-600">
                                Great score! We will skip to reading sentences next.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </LearnerLayout>
</template>

<style scoped>
/* Reset learner layout internal spacing */
:deep(.learner-stage) {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}
:deep(.learner-content) {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

/* Duolingo style 3D Pill Button */
.rd-cta-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 380px;
    padding: 1.25rem 3rem;
    background-color: #F58549; /* Primary orange */
    color: white;
    font-size: 1.15rem;
    font-weight: 900;
    letter-spacing: 0.05em;
    border-radius: 9999px;
    border: none;
    box-shadow: 0 8px 0 #c2410c; /* Deep orange shadow */
    cursor: pointer;
    transition: all 0.15s ease-in-out;
    text-transform: uppercase;
}

.rd-cta-pill:hover {
    filter: brightness(1.05);
    transform: translateY(-2px);
    box-shadow: 0 10px 0 #c2410c;
}

.rd-cta-pill:active {
    transform: translateY(8px);
    box-shadow: 0 0px 0 #c2410c;
}

/* Animations */
.anim-twinkle {
    animation: twinkle 3s ease-in-out infinite;
}
.anim-twinkle-delay {
    animation: twinkle 3s ease-in-out infinite 1.5s;
}
@keyframes twinkle {
    0%, 100% { opacity: 0.2; transform: scale(0.8); }
    50% { opacity: 1; transform: scale(1.2); }
}

.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 100ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 200ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 300ms; }
@keyframes staggerIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
