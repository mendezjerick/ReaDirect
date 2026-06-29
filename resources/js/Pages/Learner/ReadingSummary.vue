<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, Star, BookOpen, Trophy, Target, Sparkles, Brain, Flag } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentVideoPlayer from '../../Components/Agents/AgentVideoPlayer.vue';
import AgentSpeakerTTS from '../../Components/Agents/AgentSpeakerTTS.vue';

const props = defineProps({ attempt: Object });

const evaluatorMessage = computed(() => {
    return 'I used your final reading score to find your reading level. Tap continue when you are ready to see your path.';
});
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
            body: JSON.stringify({ agent: 'evaluator', text, line_key: 'estelle.result.reading_summary' }),
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
</script>

<template>
    <LearnerLayout :progress="94" diagnostic-step="sentence-reading" class="rd-full-layout">
        
        <!-- Fixed full-bleed background layer to eradicate any parent whitespace -->
        <div class="fixed inset-0 z-[1] pointer-events-none bg-gradient-to-b from-[#e6f2ff] to-[#f4f9ff]"></div>

        <!-- Main Content Container -->
        <div class="rd-celebration-bg relative z-[2] w-full px-4 pb-12 pt-6 anim-stagger">
            
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
                <div class="mb-6 flex flex-col items-center justify-center">
                    
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
                        <Check class="size-4 stroke-[3]" /> All Steps Completed
                    </div>
                    <h1 class="text-3xl font-black text-[#1e293b] sm:text-4xl">Reading check complete.</h1>
                    <p class="mt-2 text-base font-bold text-slate-500 sm:text-lg">Great job! Here is a summary of your performance.</p>
                </div>

                <!-- Split Content Layout -->
                <div class="grid gap-6 lg:grid-cols-[1fr_400px] xl:grid-cols-[1fr_450px]">
                    
                    <!-- Left Column (Scores) -->
                    <div class="flex flex-col gap-6">
                        
                        <!-- Metric cards grid -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Incorrect Words -->
                            <div class="flex flex-col justify-center gap-2 rounded-[24px] border-2 border-slate-100 bg-white px-4 py-4 shadow-sm transition-transform hover:-translate-y-1 sm:px-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-blue-100 text-blue-500">
                                        <BookOpen class="size-6" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 sm:text-[11px]">Incorrect Words</p>
                                        <p class="text-2xl font-black leading-none text-slate-800 sm:text-3xl">{{ attempt.incorrect_words }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Accuracy -->
                            <div class="flex flex-col justify-center gap-2 rounded-[24px] border-2 border-slate-100 bg-white px-4 py-4 shadow-sm transition-transform hover:-translate-y-1 sm:px-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-500">
                                        <Target class="size-6" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 sm:text-[11px]">Accuracy</p>
                                        <p class="text-2xl font-black leading-none text-slate-800 sm:text-3xl">
                                            {{ attempt.reading_accuracy }}<span class="ml-0.5 text-lg text-slate-500 sm:text-xl">%</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                                    <div class="h-full rounded-full bg-emerald-500 transition-all duration-500 ease-out" :style="{ width: `${Math.min(attempt.reading_accuracy, 100)}%` }" />
                                </div>
                            </div>

                            <!-- Comprehension -->
                            <div class="flex flex-col justify-center gap-2 rounded-[24px] border-2 border-slate-100 bg-white px-4 py-4 shadow-sm transition-transform hover:-translate-y-1 sm:px-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-violet-500">
                                        <Brain class="size-6" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 sm:text-[11px]">Comprehension</p>
                                        <p class="text-2xl font-black leading-none text-slate-800 sm:text-3xl">
                                            {{ attempt.comprehension_percentage }}<span class="ml-0.5 text-lg text-slate-500 sm:text-xl">%</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                                    <div class="h-full rounded-full bg-violet-500 transition-all duration-500 ease-out" :style="{ width: `${Math.min(attempt.comprehension_percentage, 100)}%` }" />
                                </div>
                            </div>

                            <!-- Final Reading Score -->
                            <div class="flex flex-col justify-center gap-2 rounded-[24px] border-2 border-slate-100 bg-white px-4 py-4 shadow-sm transition-transform hover:-translate-y-1 sm:px-5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-500">
                                        <Star class="size-6" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400 sm:text-[11px]">Final Reading Score</p>
                                        <p class="text-2xl font-black leading-none text-slate-800 sm:text-3xl">{{ attempt.final_reading_score }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div class="mt-4 flex w-full">
                            <Link href="/learner/diagnostic/module-placement" class="w-full">
                                <button class="rd-cta-pill w-full">
                                    SEE MY PATH
                                    <ArrowRight class="ml-2 size-6 stroke-[3]" />
                                </button>
                            </Link>
                        </div>
                    </div>

                    <!-- Right Column (Levels & Explanation) -->
                    <div class="flex flex-col gap-4">
                        <!-- Reading Level -->
                        <div class="flex flex-col items-start rounded-[24px] border-2 border-slate-100 bg-white p-5 shadow-sm">
                            <div class="mb-3 inline-flex items-center gap-2 rounded-xl bg-violet-50 px-2.5 py-1.5">
                                <Trophy class="size-4 text-violet-500" />
                                <span class="text-[11px] font-black uppercase tracking-wider text-violet-600">Reading Level</span>
                            </div>
                            <p class="text-2xl font-black text-slate-800">{{ attempt?.reading_classification }}</p>
                            <p class="mt-1 text-sm font-bold leading-relaxed text-slate-500">This is your current reading level based on your performance.</p>
                        </div>

                        <!-- Agent explanation card -->
                        <div class="rounded-[24px] border-2 border-blue-200 bg-[#f4f9ff] p-5 shadow-sm">
                            <div class="mb-2 flex items-center gap-2">
                                <Flag class="size-4 text-blue-500" />
                                <h3 class="text-[15px] font-black text-[#1e293b]">How it works</h3>
                            </div>
                            <p class="text-[13px] font-bold leading-relaxed text-slate-600">Accuracy comes from the passage word-error count. Reading level is based on the final reading score.</p>
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
