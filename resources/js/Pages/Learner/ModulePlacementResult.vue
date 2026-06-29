<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Star, BookOpen, Trophy, Target, Sparkles, Shapes, MessageCircle, FileText, Percent, BrainCircuit } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentVideoPlayer from '../../Components/Agents/AgentVideoPlayer.vue';
import AgentSpeakerTTS from '../../Components/Agents/AgentSpeakerTTS.vue';

const props = defineProps({ attempt: Object, decision: Object, module: Object });

const moduleTitle = computed(() => props.module?.title ?? 'Reading at Grade Level');

const topMetrics = computed(() => [
    { label: 'Task 1 Letters', value: props.attempt?.task_1_score ?? '-', icon: Shapes, color: 'text-blue-500', bg: 'bg-blue-100' },
    { label: 'Task 2A Rhymes', value: props.attempt?.task_2a_score ?? '-', icon: MessageCircle, color: 'text-violet-500', bg: 'bg-violet-100' },
    { label: 'Task 2B Words', value: props.attempt?.task_2b_score ?? '-', icon: FileText, color: 'text-emerald-500', bg: 'bg-emerald-100' },
    { label: 'CRLA Total', value: props.attempt?.crla_total_score ?? '-', icon: Star, color: 'text-amber-500', bg: 'bg-amber-100' },
]);

const bottomMetrics = computed(() => [
    { label: 'Passage Accuracy', value: props.attempt?.reading_accuracy ?? '-', suffix: '%', icon: Percent, color: 'text-cyan-500', bg: 'bg-cyan-100' },
    { label: 'Comprehension', value: props.attempt?.comprehension_percentage ?? '-', suffix: '%', icon: BrainCircuit, color: 'text-pink-500', bg: 'bg-pink-100' },
    { label: 'Reading Score', value: props.attempt?.final_reading_score ?? '-', suffix: '%', icon: Target, color: 'text-indigo-500', bg: 'bg-indigo-100' },
]);

const evaluatorMessage = computed(() => {
    if (props.module) {
        return 'Great job. Your reading path is ready, and it will guide the next activities on your dashboard.';
    }
    return 'Wonderful work. You are reading at grade level, so you can continue to your dashboard.';
});
const evaluatorLineKey = computed(() => props.module
    ? 'estelle.result.module_placement'
    : 'estelle.result.grade_level_placement');
const spokenEvaluatorMessage = computed(() => String(voicePayload.value?.text ?? '').trim() || evaluatorMessage.value);

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
    <LearnerLayout :progress="100" diagnostic-step="sentence-reading" class="rd-full-layout">
        
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
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-orange-100 px-4 py-1.5 text-[14px] font-black text-orange-600 shadow-sm">
                        <Star class="size-4 fill-orange-500" /> Path Ready
                    </div>
                    <h1 class="text-3xl font-black text-[#1e293b] sm:text-[42px]">Your reading path is ready.</h1>
                    <p class="mt-3 text-lg font-bold text-slate-500">Great job! You're all set for your personalized reading journey.</p>
                </div>

                <!-- Split Content Layout -->
                <div class="grid gap-6 lg:grid-cols-[1fr_400px] xl:grid-cols-[1fr_450px]">
                    
                    <!-- Left Column (Module & Scores) -->
                    <div class="flex flex-col gap-6">
                        <!-- Assigned Module Card -->
                        <div class="group relative overflow-hidden rounded-[28px] border-2 border-slate-100 bg-white p-6 shadow-[0_8px_24px_rgba(0,0,0,0.06)] transition-colors hover:border-orange-200">
                            <div class="absolute -right-10 -top-10 h-40 w-40 scale-100 rounded-full bg-orange-50 opacity-50 transition-transform duration-500 group-hover:scale-125" />
                            <div class="relative flex items-center gap-5">
                                <div class="flex h-[72px] w-[72px] shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#F58549] to-purple-500 text-white shadow-lg shadow-orange-500/30">
                                    <Check class="size-10 stroke-[4]" />
                                </div>
                                <div>
                                    <span class="mb-1 block text-[11px] font-black uppercase tracking-wider text-orange-500">Assigned Module</span>
                                    <h2 class="text-2xl font-black text-slate-800">{{ moduleTitle }}</h2>
                                    <p class="mt-1 font-bold text-slate-500">{{ decision.decision_reason }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Score Stats Grid -->
                        <div>
                            <!-- Row 1 -->
                            <div class="mb-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div v-for="metric in topMetrics" :key="metric.label" class="flex flex-col items-center justify-center rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_4px_12px_rgba(0,0,0,0.04)] transition-transform hover:-translate-y-1">
                                    <div :class="['flex h-10 w-10 items-center justify-center rounded-xl mb-2', metric.bg, metric.color]">
                                        <component :is="metric.icon" class="size-5 stroke-[2.5]" />
                                    </div>
                                    <p class="text-2xl font-black text-slate-800">{{ metric.value }}</p>
                                    <p class="mt-1 text-center text-[10px] font-black uppercase tracking-wider text-slate-400">{{ metric.label }}</p>
                                </div>
                            </div>

                            <!-- Row 2 -->
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div v-for="metric in bottomMetrics" :key="metric.label" class="flex items-center gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_4px_12px_rgba(0,0,0,0.04)] transition-transform hover:-translate-y-1">
                                    <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-xl', metric.bg, metric.color]">
                                        <component :is="metric.icon" class="size-6 stroke-[2.5]" />
                                    </div>
                                    <div>
                                        <p class="text-2xl font-black text-slate-800">{{ metric.value }}<span v-if="metric.suffix" class="ml-0.5 text-lg text-slate-400">{{ metric.suffix }}</span></p>
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ metric.label }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div class="mt-4 flex w-full">
                            <Link href="/learner/dashboard" class="w-full">
                                <button class="rd-cta-pill w-full">
                                    CONTINUE TO MY DASHBOARD
                                    <ArrowRight class="ml-2 size-6 stroke-[3]" />
                                </button>
                            </Link>
                        </div>
                    </div>

                    <!-- Right Column (Levels & Explanation) -->
                    <div class="flex flex-col gap-4">
                        <!-- CRLA Level -->
                        <div class="flex flex-col items-start rounded-[28px] border-2 border-slate-100 bg-white p-6 shadow-[0_4px_16px_rgba(0,0,0,0.05)]">
                            <div class="mb-4 inline-flex items-center gap-2 rounded-xl bg-blue-50 px-3 py-2">
                                <BookOpen class="size-5 text-blue-500" />
                                <span class="text-xs font-black uppercase tracking-wider text-blue-600">CRLA Level</span>
                            </div>
                            <p class="text-2xl font-black text-slate-800">{{ attempt?.crla_classification }}</p>
                            <p class="mt-2 text-sm font-bold leading-relaxed text-slate-500">{{ decision.crla_meaning }}</p>
                        </div>

                        <!-- Reading Level -->
                        <div class="flex flex-col items-start rounded-[28px] border-2 border-slate-100 bg-white p-6 shadow-[0_4px_16px_rgba(0,0,0,0.05)]">
                            <div class="mb-4 inline-flex items-center gap-2 rounded-xl bg-violet-50 px-3 py-2">
                                <Trophy class="size-5 text-violet-500" />
                                <span class="text-xs font-black uppercase tracking-wider text-violet-600">Reading Level</span>
                            </div>
                            <p class="text-2xl font-black text-slate-800">{{ attempt?.reading_classification }}</p>
                            <p class="mt-2 text-sm font-bold leading-relaxed text-slate-500">{{ decision.reading_meaning }}</p>
                        </div>

                        <!-- Why This Path -->
                        <div class="rounded-[28px] border-2 border-orange-200 bg-[#FFFDF7] p-6 shadow-sm">
                            <div class="mb-3 flex items-center gap-2">
                                <Sparkles class="size-5 text-[#F58549]" />
                                <h3 class="text-lg font-black text-[#1e293b]">Why This Path?</h3>
                            </div>
                            <p class="text-[14px] font-bold leading-relaxed text-slate-600">{{ decision.placement_explanation }}</p>
                            <div class="mt-4 inline-flex items-center rounded-lg bg-orange-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-orange-800">
                                Rule applied: {{ decision.rule_applied }}
                            </div>
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
    animation: twinkle 2s ease-in-out infinite alternate;
}
.anim-twinkle-delay {
    animation: twinkle 2.5s ease-in-out infinite alternate 1s;
}

@keyframes twinkle {
    0% { opacity: 0.3; transform: scale(0.8) rotate(0deg); }
    100% { opacity: 1; transform: scale(1.2) rotate(15deg); }
}

.anim-stagger > * {
    animation: staggerIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.anim-stagger > *:nth-child(1) { animation-delay: 50ms; }
.anim-stagger > *:nth-child(2) { animation-delay: 150ms; }
.anim-stagger > *:nth-child(3) { animation-delay: 250ms; }
.anim-stagger > *:nth-child(4) { animation-delay: 350ms; }
.anim-stagger > *:nth-child(5) { animation-delay: 450ms; }

@keyframes staggerIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    .anim-stagger > * {
        animation: none;
        opacity: 1;
        transform: none;
    }
    .rd-cta-pill {
        transition: none;
    }
    .rd-cta-pill:hover, .rd-cta-pill:active {
        transform: none;
    }
    .anim-twinkle, .anim-twinkle-delay {
        animation: none;
    }
}
</style>
