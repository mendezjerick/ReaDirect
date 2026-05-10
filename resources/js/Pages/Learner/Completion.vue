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
        <section class="completion-shell mx-auto grid w-full gap-3">
            <header class="completion-hero relative overflow-hidden rounded-[28px] border border-blue-100 bg-white p-5 shadow-xl shadow-primary/10">
                <div class="absolute right-5 top-4 hidden text-yellow-300 md:block" aria-hidden="true">
                    <Sparkles class="size-12" />
                </div>
                <div class="relative grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-yellow-100 px-4 py-2 text-sm font-black text-yellow-700">
                            <Trophy class="size-4" />
                            Reading Journey Complete
                        </div>
                        <h1 class="mt-3 text-4xl font-black leading-none text-text md:text-5xl xl:text-6xl">Congratulations!</h1>
                        <p class="mt-2 text-2xl font-black text-primary md:text-3xl">You completed your reading journey.</p>
                        <p class="mt-2 max-w-3xl text-base font-bold leading-relaxed text-muted md:text-lg">
                            {{ firstName }}, you worked hard from your first assessment to your final reading check. Great job!
                        </p>
                    </div>
                    <div class="hidden size-24 place-items-center rounded-[24px] bg-primary text-white shadow-lg shadow-primary/25 md:grid">
                        <Award class="size-12" />
                    </div>
                </div>
            </header>

            <div class="completion-grid grid gap-3 lg:grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)]">
                <section class="grid min-h-0 gap-3">
                    <div class="grid gap-3 md:grid-cols-3">
                        <article
                            v-for="card in cards"
                            :key="card.title"
                            class="completion-card rounded-[22px] border bg-white p-4 shadow-lg shadow-primary/5"
                            :class="{
                                'border-blue-100': card.kind !== 'progress',
                                'border-emerald-200 bg-emerald-50/60': card.kind === 'progress',
                            }"
                        >
                            <div class="flex items-center gap-2">
                                <CheckCircle2 class="size-5 text-primary" />
                                <h2 class="text-lg font-black leading-tight text-text">{{ card.title }}</h2>
                            </div>
                            <div v-if="card.metrics?.length" class="mt-3 grid gap-2">
                                <div v-for="metric in card.metrics" :key="metric.label" class="rounded-2xl bg-white/80 px-3 py-2 ring-1 ring-blue-50">
                                    <p class="text-[11px] font-black uppercase text-muted">{{ metric.label }}</p>
                                    <p class="mt-0.5 text-xl font-black text-text">{{ metric.value }}</p>
                                </div>
                            </div>
                            <p v-else class="mt-3 rounded-2xl bg-blue-50 px-3 py-2 text-sm font-bold text-primary">
                                {{ resultSummary.fallbackMessage }}
                            </p>
                            <p v-if="card.message" class="mt-3 text-sm font-bold leading-snug text-emerald-700">
                                {{ card.message }}
                            </p>
                        </article>
                    </div>
                    <p v-if="!hasAnyMetrics" class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-center text-base font-black text-primary">
                        {{ resultSummary.fallbackMessage }}
                    </p>
                </section>

                <section class="rounded-[24px] border border-blue-100 bg-white p-4 shadow-lg shadow-primary/5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-xl font-black text-text">Your agents are proud of you</h2>
                        <button
                            type="button"
                            class="grid size-10 place-items-center rounded-full bg-blue-50 text-primary transition hover:bg-primary hover:text-white"
                            aria-label="Stop voice"
                            @click="stopVoices"
                        >
                            <VolumeX class="size-5" />
                        </button>
                    </div>

                    <div class="mt-3 grid gap-3">
                        <article
                            v-for="agent in agentMessages"
                            :key="agent.name"
                            class="rounded-[20px] border border-border bg-background p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black uppercase text-primary">{{ agent.name }}</p>
                                    <p class="text-xs font-bold text-muted">{{ agent.role }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-full bg-primary px-3 py-2 text-xs font-black text-white shadow-md shadow-primary/25 transition hover:bg-primary-dark disabled:opacity-70"
                                    :disabled="activeAgent === agent.agentType"
                                    @click="playAgent(agent)"
                                >
                                    <Volume2 class="size-4" />
                                    {{ activeAgent === agent.agentType ? 'Playing' : 'Play Voice' }}
                                </button>
                            </div>
                            <p v-if="agent.resultMessage" class="mt-2 text-sm font-black leading-snug text-text">
                                {{ agent.resultMessage }}
                            </p>
                            <p class="mt-2 text-base font-black leading-snug text-text">
                                {{ agent.message }}
                            </p>
                        </article>
                    </div>

                    <p v-if="voiceStatus" class="mt-3 rounded-2xl bg-blue-50 px-3 py-2 text-sm font-bold text-primary">
                        {{ voiceStatus }}
                    </p>
                </section>
            </div>

            <footer class="completion-footer grid gap-2 rounded-[26px] border border-blue-100 bg-white p-4 text-center shadow-xl shadow-primary/10">
                <p class="text-sm font-bold text-muted">You may now return to the home page.</p>
                <button
                    type="button"
                    class="mx-auto inline-flex w-full max-w-xl items-center justify-center gap-3 rounded-[24px] bg-primary px-8 py-5 text-3xl font-black text-white shadow-xl shadow-primary/30 transition hover:-translate-y-0.5 hover:bg-primary-dark disabled:opacity-70"
                    :disabled="form.processing"
                    @click="submitThankYou"
                >
                    <Home class="size-8" />
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

    .completion-card {
        min-height: 100%;
    }
}

@media (prefers-reduced-motion: no-preference) {
    .completion-hero::before,
    .completion-hero::after {
        position: absolute;
        content: '';
        border-radius: 9999px;
        background: rgba(250, 204, 21, 0.22);
        animation: float-star 5s ease-in-out infinite;
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

    @keyframes float-star {
        0%, 100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-0.45rem);
        }
    }
}
</style>
