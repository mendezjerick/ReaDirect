<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Award, CheckCircle2, Home, Sparkles, Trophy, Volume2, VolumeX } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';

const props = defineProps({
    learner: { type: Object, default: null },
    resultSummary: { type: Object, default: () => ({ cards: [], fallbackMessage: 'Your final reading check has been completed.' }) },
    agentMessages: { type: Array, default: () => [] },
    thankYouUrl: { type: String, default: '/learner/completion/thank-you' },
    homeUrl: { type: String, default: '/' },
});

const form = useForm({});
const activeAudio = ref(null);
const activeAgent = ref(null);
const voiceStatus = ref('');

const firstName = computed(() => props.learner?.first_name ?? 'Friend');
const cards = computed(() => props.resultSummary?.cards ?? []);
const hasAnyMetrics = computed(() => cards.value.some((card) => (card.metrics ?? []).length > 0));

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const stopVoices = () => {
    if (activeAudio.value) {
        activeAudio.value.pause();
        activeAudio.value.currentTime = 0;
        activeAudio.value = null;
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-audio'));
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
    activeAgent.value = null;
};

const playAgent = async (agent) => {
    const message = [agent.resultMessage, agent.message].filter(Boolean).join(' ');
    const agentKey = agent.agentType;

    stopVoices();
    activeAgent.value = agentKey;
    voiceStatus.value = '';

    try {
        const response = await fetch('/agent-voice/synthesize', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                agent: agent.agentType,
                text: message,
            }),
        });

        const payload = response.ok ? await response.json() : {};

        if (payload.audio_url) {
            const audio = new Audio(payload.audio_url);
            activeAudio.value = audio;
            audio.onended = () => {
                if (activeAgent.value === agentKey) {
                    activeAgent.value = null;
                }
                activeAudio.value = null;
            };
            audio.onerror = () => {
                if (activeAgent.value === agentKey) {
                    voiceStatus.value = 'Voice is unavailable, but you can read each message here.';
                    activeAgent.value = null;
                }
                activeAudio.value = null;
            };
            await audio.play();
            return;
        }
    } catch {
        // Visible text is enough for the learner flow.
    }

    voiceStatus.value = 'Voice is unavailable, but you can read each message here.';
    activeAgent.value = null;
};

const submitThankYou = () => {
    stopVoices();
    form.post(props.thankYouUrl, {
        preserveScroll: false,
    });
};

onBeforeUnmount(stopVoices);
</script>

<template>
    <LearnerLayout :progress="100">
        <section class="completion-shell relative mx-auto grid w-full gap-5">
            <!-- Decorative blur blobs -->
            <div class="pointer-events-none absolute -left-20 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-16 top-52 h-40 w-40 rounded-full bg-blue-500/5 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute bottom-20 left-1/4 h-40 w-40 rounded-full bg-amber-400/5 blur-3xl" aria-hidden="true" />

            <!-- Hero celebration card -->
            <header class="anim-card completion-hero relative overflow-hidden rounded-[36px] border-[3px] border-primary/10 bg-white p-6 shadow-2xl shadow-primary/10 sm:p-8">
                <!-- Sparkle decorations -->
                <span class="pointer-events-none absolute left-6 top-4 text-4xl font-black text-primary/5" aria-hidden="true">✦</span>
                <span class="pointer-events-none absolute right-20 bottom-4 text-3xl font-black text-primary/5" aria-hidden="true">✦</span>
                <div class="pointer-events-none absolute right-5 top-4 hidden text-amber-300/60 md:block" aria-hidden="true">
                    <Sparkles class="size-12 drop-shadow-sm" />
                </div>

                <div class="relative grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <!-- Trophy badge -->
                        <div class="inline-flex items-center gap-2.5 rounded-full bg-gradient-to-r from-amber-100 to-yellow-100 px-4 py-2 text-[14px] font-black text-amber-700 ring-1 ring-amber-200/50">
                            <Trophy class="size-4" />
                            Reading Journey Complete
                        </div>
                        <h1 class="anim-pop mt-4 bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-4xl font-black leading-none text-transparent md:text-5xl xl:text-6xl">
                            Congratulations!
                        </h1>
                        <p class="mt-3 text-2xl font-black md:text-3xl">
                            <span class="bg-gradient-to-r from-primary to-blue-600 bg-clip-text text-transparent">You completed your reading journey.</span>
                        </p>
                        <p class="mt-3 max-w-3xl text-[15px] font-semibold leading-relaxed text-slate-500 md:text-base">
                            {{ firstName }}, you worked hard from your first assessment to your final reading check. Great job!
                        </p>
                    </div>
                    <div class="hidden size-24 place-items-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-lg shadow-primary/20 md:grid">
                        <Award class="size-12" />
                    </div>
                </div>
            </header>

            <!-- Main content grid -->
            <div class="completion-grid grid gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)]">
                <!-- Score cards section -->
                <section class="grid min-h-0 gap-4">
                    <div class="anim-stagger grid gap-4 md:grid-cols-3">
                        <article
                            v-for="card in cards"
                            :key="card.title"
                            class="rounded-[28px] border bg-white p-5 shadow-xl shadow-slate-200/30"
                            :class="{
                                'border-slate-200/80': card.kind !== 'progress',
                                'border-emerald-200/80 bg-emerald-50/40': card.kind === 'progress',
                            }"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-8 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-sm shadow-primary/20">
                                    <CheckCircle2 class="size-4" />
                                </span>
                                <h2 class="text-[16px] font-black leading-tight text-slate-800">{{ card.title }}</h2>
                            </div>
                            <div v-if="card.metrics?.length" class="mt-4 grid gap-2.5">
                                <div v-for="metric in card.metrics" :key="metric.label" class="rounded-[20px] border border-slate-200/60 bg-slate-50/50 px-4 py-3 shadow-sm">
                                    <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">{{ metric.label }}</p>
                                    <p class="mt-1 text-xl font-black text-slate-800">{{ metric.value }}</p>
                                </div>
                            </div>
                            <p v-else class="mt-4 rounded-[20px] border border-slate-200/60 bg-slate-50/50 px-4 py-3 text-[14px] font-semibold text-slate-500 shadow-sm">
                                {{ resultSummary.fallbackMessage }}
                            </p>
                            <p v-if="card.message" class="mt-3 text-[13px] font-black leading-snug text-emerald-600">
                                {{ card.message }}
                            </p>
                        </article>
                    </div>
                    <p v-if="!hasAnyMetrics" class="anim-slide-up rounded-[24px] border border-slate-200/60 bg-slate-50/50 px-5 py-4 text-center text-[15px] font-black text-primary shadow-sm">
                        {{ resultSummary.fallbackMessage }}
                    </p>
                </section>

                <!-- Agent messages panel -->
                <section class="anim-slide-up rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-xl shadow-slate-200/30">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-[16px] font-black text-slate-800">Your agents are proud of you</h2>
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-2xl bg-slate-50 text-slate-400 ring-1 ring-slate-200/60 transition hover:bg-gradient-to-br hover:from-primary hover:to-blue-600 hover:text-white hover:ring-0"
                            aria-label="Stop voice"
                            @click="stopVoices"
                        >
                            <VolumeX class="size-5" />
                        </button>
                    </div>

                    <div class="mt-4 grid gap-3">
                        <article
                            v-for="agent in agentMessages"
                            :key="agent.name"
                            class="rounded-[24px] border border-slate-200/60 bg-slate-50/50 p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[14px] font-black uppercase tracking-widest text-primary">{{ agent.name }}</p>
                                    <p class="text-[12px] font-semibold text-slate-400">{{ agent.role }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-[20px] bg-gradient-to-r from-primary to-blue-600 px-4 py-2.5 text-[12px] font-black text-white shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/30 disabled:opacity-70 disabled:hover:translate-y-0"
                                    :disabled="activeAgent === agent.agentType"
                                    @click="playAgent(agent)"
                                >
                                    <Volume2 class="size-4" />
                                    {{ activeAgent === agent.agentType ? 'Playing' : 'Play Voice' }}
                                </button>
                            </div>
                            <p v-if="agent.resultMessage" class="mt-3 text-[14px] font-black leading-snug text-slate-800">
                                {{ agent.resultMessage }}
                            </p>
                            <p class="mt-2 text-[15px] font-black leading-snug text-slate-800">
                                {{ agent.message }}
                            </p>
                        </article>
                    </div>

                    <p v-if="voiceStatus" class="mt-4 rounded-[20px] bg-amber-50 px-4 py-3 text-[13px] font-semibold text-amber-700 ring-1 ring-amber-200/60">
                        {{ voiceStatus }}
                    </p>
                </section>
            </div>

            <!-- Footer action -->
            <footer class="anim-slide-up completion-footer grid gap-3 rounded-[32px] border border-slate-200/80 bg-white p-5 text-center shadow-xl shadow-slate-200/30 sm:p-6">
                <p class="text-[14px] font-semibold text-slate-400">You may now return to the home page.</p>
                <button
                    type="button"
                    class="mx-auto inline-flex w-full max-w-xl items-center justify-center gap-3 rounded-[20px] bg-gradient-to-r from-primary to-blue-600 px-8 py-5 text-2xl font-black text-white shadow-xl shadow-primary/20 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-2xl hover:shadow-primary/30 focus:outline-none focus:ring-4 focus:ring-primary/20 active:scale-[0.98] active:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:scale-100 sm:text-3xl"
                    :disabled="form.processing"
                    @click="submitThankYou"
                >
                    <Home class="size-7 sm:size-8" />
                    Thank You
                </button>
            </footer>
        </section>
    </LearnerLayout>
</template>

<style scoped>
.completion-shell {
    max-width: min(100%, 88rem);
}

@media (min-width: 1024px) {
    .completion-shell {
        min-height: calc(100svh - 8rem);
        grid-template-rows: auto minmax(0, 1fr) auto;
    }

    .completion-grid {
        min-height: 0;
    }
}

/* Card spring entrance */
.anim-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Content pop (for large text/letters) */
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

/* Hero celebration decorations */
@media (prefers-reduced-motion: no-preference) {
    .completion-hero::before,
    .completion-hero::after {
        position: absolute;
        content: '';
        border-radius: 9999px;
        background: rgba(59, 130, 246, 0.08);
        animation: floatStar 5s ease-in-out infinite;
    }

    .completion-hero::before {
        left: 1.25rem;
        top: 1rem;
        width: 0.8rem;
        height: 0.8rem;
    }

    .completion-hero::after {
        right: 8rem;
        bottom: 1.5rem;
        width: 1rem;
        height: 1rem;
        animation-delay: 1.2s;
    }

    @keyframes floatStar {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-0.45rem);
        }
    }
}
</style>
