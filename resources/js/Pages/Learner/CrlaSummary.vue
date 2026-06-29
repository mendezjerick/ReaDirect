<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Star, BookOpen, Clock3, Music, WholeWord, Sparkles, Shapes, Check } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentVideoPlayer from '../../Components/Agents/AgentVideoPlayer.vue';
import AgentSpeakerTTS from '../../Components/Agents/AgentSpeakerTTS.vue';

const props = defineProps({
    attempt: Object,
    placementPreview: Object,
    taskTwoBReview: Object,
    passageEligible: Boolean,
});

const topMetrics = computed(() => [
    { label: 'Task 1 Letters', value: props.attempt?.task_1_score ?? '-', icon: Shapes, color: 'text-blue-500', bg: 'bg-blue-100' },
    { label: 'Task 2A Rhymes', value: props.attempt?.task_2a_score ?? '-', icon: Music, color: 'text-violet-500', bg: 'bg-violet-100' },
    { label: 'Task 2B Words', value: props.attempt?.task_2b_score ?? '-', icon: BookOpen, color: 'text-emerald-500', bg: 'bg-emerald-100' },
]);

const evaluatorMessage = computed(() => {
    return props.passageEligible
        ? 'The CRLA tasks are complete. Review your scores first, then you will continue with a short reading passage.'
        : 'The CRLA tasks are complete. Passage reading is not needed for this result, so we can move forward.';
});
const evaluatorLineKey = computed(() => props.passageEligible
    ? 'estelle.result.crla.summary_with_passage'
    : 'estelle.result.crla.summary_no_passage');
const spokenEvaluatorMessage = computed(() => String(voicePayload.value?.text ?? '').trim() || evaluatorMessage.value);

const accuracyTone = (percentage) => {
    if (percentage >= 90) return 'text-emerald-600 bg-emerald-50 border-emerald-200';
    if (percentage >= 75) return 'text-blue-600 bg-blue-50 border-blue-200';
    if (percentage >= 60) return 'text-amber-600 bg-amber-50 border-amber-200';
    return 'text-rose-600 bg-rose-50 border-rose-200';
};

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
            body: JSON.stringify({ agent: 'evaluator', text, line_key: evaluatorLineKey.value }),
        });

        if (requestId !== voiceRequestId.value) return;
        voicePayload.value = response.ok ? await response.json() : { audio_url: null };
    } catch {
        if (requestId === voiceRequestId.value) voicePayload.value = { audio_url: null };
    } finally {
        if (requestId === voiceRequestId.value) voiceLoading.value = false;
    }
};

onMounted(() => loadNaturalVoice());

const replayVoice = () => {
    isMuted.value = false;
    ttsKey.value++;
};
</script>

<template>
    <LearnerLayout :progress="65" diagnostic-step="task-2b" class="rd-full-layout">
        
        <!-- Fixed full-bleed background layer to eradicate any parent whitespace -->
        <div class="fixed inset-0 z-[1] pointer-events-none bg-gradient-to-b from-[#e6f2ff] to-[#f4f9ff]"></div>

        <!-- Main Content Container -->
        <div class="rd-celebration-bg relative z-[2] w-full px-4 pb-20 pt-10 anim-stagger">
            
            <!-- Background Decorative Elements -->
            <div class="absolute top-10 left-[10%] opacity-60">
                <div class="h-12 w-32 rounded-full bg-white blur-[2px]"></div>
                <div class="absolute -top-4 left-4 h-16 w-16 rounded-full bg-white blur-[2px]"></div>
                <div class="absolute -top-2 right-4 h-12 w-12 rounded-full bg-white blur-[2px]"></div>
            </div>
            <div class="absolute top-24 right-[15%] opacity-60 scale-75">
                <div class="h-12 w-32 rounded-full bg-white blur-[2px]"></div>
                <div class="absolute -top-4 left-4 h-16 w-16 rounded-full bg-white blur-[2px]"></div>
                <div class="absolute -top-2 right-4 h-12 w-12 rounded-full bg-white blur-[2px]"></div>
            </div>
            
            <!-- Confetti dots -->
            <div class="absolute left-[20%] top-[30%] h-3 w-3 rounded-full bg-red-400 opacity-70"></div>
            <div class="absolute right-[25%] top-[20%] h-2 w-2 rounded-full bg-blue-400 opacity-70"></div>
            <div class="absolute left-[30%] top-[60%] h-2.5 w-2.5 rounded-full bg-yellow-400 opacity-70"></div>
            <div class="absolute right-[15%] top-[50%] h-3 w-3 rounded-full bg-green-400 opacity-70"></div>
            <div class="absolute left-[10%] top-[40%] h-2 w-2 rounded-full bg-purple-400 opacity-70"></div>
            
            <!-- Main Content Container -->
            <div class="relative z-10 mx-auto max-w-[1100px]">
                
                <!-- Miss Estelle Mascot Area -->
                <div class="mb-10 flex flex-col items-center justify-center">
                    
                    <AgentSpeakerTTS
                        v-if="!voiceLoading"
                        :key="ttsKey"
                        agent-type="evaluator"
                        :message="spokenEvaluatorMessage"
                        :mute="isMuted"
                        :audio-url="voicePayload?.audio_url"
                        @speaking-start="isSpeaking = true"
                        @speaking-end="isSpeaking = false"
                    />

                    <div class="relative h-48 w-48">
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
                <div class="mb-10 text-center">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-amber-100 px-4 py-1.5 text-[14px] font-black text-amber-600 shadow-sm">
                        <Star class="size-4 fill-amber-500" /> CRLA Complete
                    </div>
                    <h1 class="text-3xl font-black text-[#1e293b] sm:text-[42px]">Your CRLA score is ready.</h1>
                    <p class="mt-3 text-lg font-bold text-slate-500">You've completed the foundational tasks.</p>
                </div>

                <!-- Split Content Layout -->
                <div class="grid gap-6 lg:grid-cols-[1fr_400px] xl:grid-cols-[1fr_450px]">
                    
                    <!-- Left Column (Module & Scores) -->
                    <div class="flex flex-col gap-6 sticky top-6 self-start">
                        <!-- Assigned Module Card (Reused for CRLA Level) -->
                        <div class="group relative overflow-hidden rounded-[28px] border-2 border-slate-100 bg-white p-6 shadow-[0_8px_24px_rgba(0,0,0,0.06)] transition-colors hover:border-blue-200">
                            <div class="absolute -right-10 -top-10 h-40 w-40 scale-100 rounded-full bg-blue-50 opacity-50 transition-transform duration-500 group-hover:scale-125" />
                            <div class="relative flex items-center gap-5">
                                <div class="flex h-[72px] w-[72px] shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600 text-white shadow-lg shadow-blue-500/30">
                                    <Star class="size-10 stroke-[3] fill-white" />
                                </div>
                                <div>
                                    <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-blue-500">CRLA Total Score</span>
                                    <h2 class="text-3xl font-black text-slate-800">{{ attempt.crla_total_score }}<span class="text-2xl text-slate-300">/30</span></h2>
                                    <p class="mt-1 font-bold text-slate-500">{{ attempt.crla_classification }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Score Stats Grid -->
                        <div>
                            <!-- Row 1 -->
                            <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div v-for="metric in topMetrics" :key="metric.label" class="flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_4px_12px_rgba(0,0,0,0.04)] transition-transform hover:-translate-y-1">
                                    <div :class="['flex h-10 w-10 items-center justify-center rounded-xl mb-2', metric.bg, metric.color]">
                                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                                    </div>
                                    <p class="text-2xl font-black text-slate-800">{{ metric.value }}</p>
                                    <p class="mt-1 text-center text-[10px] font-black uppercase tracking-wider text-slate-400">{{ metric.label }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div class="mt-4 flex w-full">
                            <Link :href="passageEligible ? '/learner/diagnostic/reading-intro' : '/learner/diagnostic/module-placement'" class="w-full">
                                <button class="rd-cta-pill w-full">
                                    {{ passageEligible ? 'CONTINUE TO PASSAGE READING' : 'CONTINUE TO MODULE PLACEMENT' }}
                                    <ArrowRight class="ml-2 size-6 stroke-[3]" />
                                </button>
                            </Link>
                        </div>
                    </div>

                    <!-- Right Column (Levels & Explanation) -->
                    <div class="flex flex-col gap-4">
                        
                        <!-- What We Noticed -->
                        <div class="flex flex-col items-start rounded-[28px] border-2 border-slate-100 bg-white p-6 shadow-[0_4px_16px_rgba(0,0,0,0.05)]">
                            <div class="mb-4 inline-flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2">
                                <Clock3 class="size-5 text-violet-500" />
                                <span class="text-xs font-black uppercase tracking-wider text-violet-600">What we noticed</span>
                            </div>
                            <p class="text-2xl font-black text-slate-800 capitalize">{{ taskTwoBReview?.feedback_label ?? 'Standard' }}</p>
                            <p class="mt-2 text-sm font-bold leading-relaxed text-slate-500">{{ placementPreview?.crla_meaning ?? 'Your performance was carefully recorded.' }}</p>
                        </div>
                        
                        <div class="flex flex-col items-start rounded-[28px] border-2 border-slate-100 bg-white p-6 shadow-[0_4px_16px_rgba(0,0,0,0.05)]">
                            <div class="mb-4 inline-flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2">
                                <Check class="size-5 text-emerald-500" />
                                <span class="text-xs font-black uppercase tracking-wider text-emerald-600">Decision Reason</span>
                            </div>
                            <p class="text-[15px] font-bold leading-relaxed text-slate-600">{{ placementPreview?.decision_reason ?? 'Completing the tasks successfully.' }}</p>
                        </div>

                        <!-- Task 2B Word Results Breakdown -->
                        <div v-if="taskTwoBReview?.items?.length" class="rounded-[28px] border-2 border-orange-200 bg-[#FFFDF7] p-6 shadow-sm">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <BookOpen class="size-5 text-[#F58549]" />
                                    <h3 class="text-lg font-black text-[#1e293b]">Task 2B Breakdown</h3>
                                </div>
                                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-black text-orange-700">{{ taskTwoBReview.average_accuracy_percentage }}% Avg</span>
                            </div>
                            
                            <!-- Scrollable list of items -->
                            <div class="mt-4 flex max-h-[400px] flex-col gap-3 overflow-y-auto pr-2 rd-custom-scrollbar">
                                <div v-for="item in taskTwoBReview.items" :key="item.item_number" class="flex items-center justify-between rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-slate-400">Item {{ item.item_number }}</p>
                                        <p class="text-[15px] font-black text-slate-800">{{ item.prompt }}</p>
                                    </div>
                                    <span :class="['rounded-full px-3 py-1 text-[13px] font-black border', accuracyTone(item.accuracy_percentage)]">
                                        {{ item.accuracy_percentage }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </LearnerLayout>
</template>

<style scoped>
/* Card animations */
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

.anim-twinkle {
    animation: twinkle 3s ease-in-out infinite;
}
.anim-twinkle-delay {
    animation: twinkle 3s ease-in-out infinite;
    animation-delay: 1.5s;
}
@keyframes twinkle {
    0%, 100% { opacity: 0.4; transform: scale(0.8) rotate(0deg); }
    50% { opacity: 1; transform: scale(1.2) rotate(15deg); }
}

/* CTA Pill Button (Reused from ModulePlacementResult) */
.rd-cta-pill {
    display: inline-flex;
    min-height: 64px;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: linear-gradient(180deg, #F58549 0%, #D9652F 100%);
    padding: 0 2.5rem;
    font-size: 1.1rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: white;
    text-transform: uppercase;
    box-shadow: 0 8px 0 #B84B24, 0 12px 24px rgba(217, 101, 47, 0.3);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.rd-cta-pill:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 0 #B84B24, 0 16px 32px rgba(217, 101, 47, 0.4);
    filter: brightness(1.05);
}
.rd-cta-pill:active {
    transform: translateY(6px);
    box-shadow: 0 2px 0 #B84B24, 0 4px 12px rgba(217, 101, 47, 0.2);
}

/* Force layout internal spacing reset */
:deep(.learner-stage) {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}
:deep(.learner-content) {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
    max-width: none !important;
}

/* Custom Scrollbar for inner elements */
.rd-custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}
.rd-custom-scrollbar::-webkit-scrollbar-track {
    background: #ffedd5; /* orange-100 */
    border-radius: 12px;
    margin: 4px 0;
}
.rd-custom-scrollbar::-webkit-scrollbar-thumb {
    background: #fdba74; /* orange-300 */
    border-radius: 12px;
    border: 2px solid #ffedd5;
}
.rd-custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #fb923c; /* orange-400 */
}
</style>

<style>
/* Global unscoped style to forcefully set the layout root element's background for this page */
.rd-full-layout {
    background: #FDFBF7 !important;
}
</style>
