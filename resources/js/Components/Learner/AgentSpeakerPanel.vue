<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { GraduationCap, RotateCcw, Volume2, VolumeX } from 'lucide-vue-next';
import AgentSpeakerTTS from '../Agents/AgentSpeakerTTS.vue';
import AgentVideoPlayer from '../Agents/AgentVideoPlayer.vue';

const props = defineProps({
    agentType: { type: String, required: true },
    state: { type: String, default: 'idle' },
    message: { type: String, required: true },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    compact: Boolean,
    showAudioButton: Boolean,
    ttsEnabled: { type: Boolean, default: true },
    defaultMuted: { type: Boolean, default: false },
    volume: { type: Number, default: 1 },
    rate: { type: Number, default: 1 },
    pitch: { type: Number, default: 1 },
    presentation: { type: String, default: 'default' },
    allowCongrats: { type: Boolean, default: false },
});

const emit = defineEmits(['interaction-ended']);

const agents = {
    assessment: {
        label: 'Miss Vivian',
        role: 'Assessment Guide',
        intro: 'Hello! I am Miss Vivian. I will guide you through your reading assessment. Try your best and answer one step at a time.',
    },
    coach_feedback: {
        label: 'Miss Ciel',
        role: 'Reading Coach',
        intro: 'Hi! I am Miss Ciel. I will help you practice reading. Mistakes are okay. I am here to guide you.',
    },
    evaluator: {
        label: 'Miss Estelle',
        role: 'Results Guide',
        intro: 'Hello! I am Miss Estelle. I will help explain your results so you know what to do next.',
    },
    evaluator_recommendation: {
        label: 'Miss Estelle',
        role: 'Results Guide',
        intro: 'Hello! I am Miss Estelle. I will help explain your results so you know what to do next.',
    },
};

const isSpeaking = ref(false);
const ttsError = ref('');
const ttsKey = ref(0);
const showIntro = ref(false);
const voicePayload = ref(null);
const voiceLoading = ref(false);
const voiceRequestId = ref(0);

const storedMutedPreference = () => {
    if (typeof window === 'undefined') {
        return props.defaultMuted;
    }

    const storedValue = window.localStorage.getItem('readirect-agent-tts-muted');

    if (storedValue === null) {
        return props.defaultMuted;
    }

    return storedValue === 'true';
};

const isMuted = ref(storedMutedPreference());

const agent = computed(() => agents[props.agentType] ?? agents.assessment);
const introStorageKey = computed(() => `readirect-agent-intro-seen-${props.agentType}`);
const displayMessage = computed(() => showIntro.value ? agent.value.intro : props.message);
const effectiveState = computed(() => (isSpeaking.value ? 'speaking' : (props.state || 'idle')));
const mediaState = computed(() => {
    const requestedState = props.state || 'idle';
    const normalizedState = requestedState === 'speaking' ? 'idle' : requestedState;
    const isCiel = props.agentType === 'coach_feedback';

    if (isCiel && isSpeaking.value && normalizedState === 'idle') {
        return 'talk';
    }

    return normalizedState;
});
const displayTitle = computed(() => props.title || agent.value.label);
const displaySubtitle = computed(() => props.subtitle || agent.value.role);
const naturalAudioUrl = computed(() => voicePayload.value?.audio_url ?? null);
const stateLabel = computed(() => {
    const labels = {
        idle: 'Ready',
        speaking: 'Speaking',
        listening: 'Listening',
        thinking: 'Thinking',
        encouraging: 'Encouraging',
        happy: 'Happy',
        celebrating: 'Celebrating',
        confused: 'Thinking',
        pointing: 'Pointing',
        neutral: 'Ready',
    };

    return labels[effectiveState.value] ?? 'Ready';
});
watch(() => props.agentType, () => {
    showIntro.value = false;
    loadIntroState();
});

watch(() => props.message, () => {
    showIntro.value = false;
});

watch(isMuted, (value) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem('readirect-agent-tts-muted', String(value));
    }
});

const toggleMute = () => {
    isMuted.value = !isMuted.value;
};

const replayMessage = () => {
    if (isMuted.value) {
        isMuted.value = false;
    }

    ttsKey.value += 1;
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const loadNaturalVoice = async () => {
    const text = displayMessage.value?.trim();
    const requestId = voiceRequestId.value + 1;
    voiceRequestId.value = requestId;
    voicePayload.value = null;

    if (!props.ttsEnabled || !text || typeof window === 'undefined') {
        return;
    }

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
            body: JSON.stringify({
                agent: props.agentType,
                text,
            }),
        });

        if (requestId !== voiceRequestId.value) {
            return;
        }

        voicePayload.value = response.ok
            ? await response.json()
            : { audio_url: null };
    } catch {
        if (requestId === voiceRequestId.value) {
            voicePayload.value = { audio_url: null };
        }
    } finally {
        if (requestId === voiceRequestId.value) {
            voiceLoading.value = false;
        }
    }
};

const handleSpeakingStart = () => {
    ttsError.value = '';
    isSpeaking.value = true;
};

const handleSpeakingEnd = () => {
    isSpeaking.value = false;
};

const handleTtsError = (message) => {
    if (!message) {
        ttsError.value = '';
        return;
    }

    if (message.toLowerCase().includes('autoplay')) {
        ttsError.value = '';
        isSpeaking.value = false;
        return;
    }

    ttsError.value = 'Voice is unavailable, but you can read the message here.';
    isSpeaking.value = false;
};

const loadIntroState = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.localStorage.getItem(introStorageKey.value) === 'true') {
        return;
    }

    showIntro.value = true;
    window.localStorage.setItem(introStorageKey.value, 'true');
};

onMounted(loadIntroState);

watch(
    () => [props.agentType, displayMessage.value, props.ttsEnabled],
    () => loadNaturalVoice(),
    { immediate: true },
);
</script>

<template>
    <section
        v-if="presentation === 'routing'"
        class="relative overflow-hidden rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 lg:p-8"
        :class="isSpeaking ? 'ring-2 ring-primary/20' : ''"
    >
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="pointer-events-none absolute -right-6 -top-6 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" />
        
        <div class="relative grid justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
            <p class="mt-6 text-center text-[16px] font-black uppercase tracking-widest text-primary">{{ displayTitle }}</p>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-4 py-2 text-sm font-black text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                    :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                    @click="toggleMute"
                >
                    <VolumeX v-if="isMuted" class="size-4" />
                    <Volume2 v-else class="size-4" />
                    {{ isMuted ? 'Muted' : stateLabel }}
                </button>
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-4 py-2 text-sm font-black text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                    aria-label="Replay agent message"
                    @click="replayMessage"
                >
                    <RotateCcw class="size-4" />
                    Replay
                </button>
            </div>
        </div>
        <div class="relative mt-6 rounded-[24px] border border-slate-200/60 bg-slate-50/80 p-5 shadow-sm">
            <p class="text-lg font-black leading-relaxed text-slate-800">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-slate-500">
                {{ ttsError }}
            </p>
        </div>
    </section>
    <section
        v-else-if="presentation === 'reading-results'"
        class="relative overflow-hidden rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 lg:p-8"
        :class="isSpeaking ? 'ring-2 ring-primary/20' : ''"
    >
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" />

        <div class="grid justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
        </div>
        <div class="relative mt-6 rounded-[24px] border border-slate-200/60 bg-slate-50/80 p-5 shadow-sm xl:mt-8">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/60 pb-4">
                <div>
                    <p class="text-base font-black uppercase tracking-widest text-primary xl:text-lg">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-0.5 text-sm font-bold text-slate-400">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-4 py-2 text-sm font-black text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        {{ stateLabel }}
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-10 place-items-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <VolumeX v-if="isMuted" class="size-5" />
                        <Volume2 v-else class="size-5" />
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-10 place-items-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-5" />
                    </button>
                </div>
            </div>
            <div class="relative mt-5">
                <p class="text-base font-bold leading-relaxed text-slate-800 xl:text-lg">
                    {{ displayMessage }}
                </p>
                <p v-if="ttsError" class="mt-2 text-xs font-bold text-slate-500">
                    {{ ttsError }}
                </p>
            </div>
        </div>
    </section>
    <section
        v-else-if="presentation === 'summary'"
        class="relative overflow-hidden rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 lg:p-8"
        :class="isSpeaking ? 'ring-2 ring-primary/20' : ''"
    >
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" />
        
        <div class="relative grid justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
            <p class="mt-6 text-center text-[16px] font-black uppercase tracking-widest text-primary">{{ displayTitle }}</p>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-4 py-2 text-sm font-black text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                    :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                    @click="toggleMute"
                >
                    <VolumeX v-if="isMuted" class="size-4" />
                    <Volume2 v-else class="size-4" />
                    {{ isMuted ? 'Muted' : stateLabel }}
                </button>
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-4 py-2 text-sm font-black text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                    aria-label="Replay agent message"
                    @click="replayMessage"
                >
                    <RotateCcw class="size-4" />
                    Replay
                </button>
            </div>
        </div>
        <div class="relative mt-6 rounded-[24px] border border-slate-200/60 bg-slate-50/80 p-5 shadow-sm">
            <p class="text-[16px] font-bold leading-relaxed text-slate-800">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-slate-500">
                {{ ttsError }}
            </p>
        </div>
    </section>
    <section
        v-else-if="presentation === 'comprehension'"
        class="grid gap-0 rounded-[28px] bg-surface p-4 shadow-xl shadow-primary/10 sm:p-5"
        :class="isSpeaking ? 'ring-2 ring-primary/20' : ''"
    >
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="grid justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
        </div>
        <div class="relative mt-4 rounded-[22px] border border-blue-200 bg-surface p-4 shadow-sm shadow-primary/10 sm:mt-5 sm:p-5 xl:rounded-[26px] xl:p-6">
            <span class="absolute left-1/2 top-0 size-8 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-blue-200 bg-surface" aria-hidden="true" />
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-full bg-primary-light text-primary">
                        <GraduationCap class="size-6" />
                    </span>
                    <div>
                        <p class="text-lg font-black uppercase text-primary xl:text-xl">{{ displayTitle }}</p>
                        <p v-if="displaySubtitle" class="text-sm font-black text-muted">{{ displaySubtitle }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-0">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-l-full rounded-r-none bg-primary-light px-3 py-2 text-sm font-black text-primary transition hover:bg-primary hover:text-white xl:px-4 xl:text-base"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        {{ stateLabel }}
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-11 place-items-center rounded-r-full bg-primary-light text-primary transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <VolumeX v-if="isMuted" class="size-6" />
                        <Volume2 v-else class="size-6" />
                    </button>
                </div>
            </div>
            <p class="mt-5 text-xl font-black leading-relaxed text-text xl:mt-7 xl:text-2xl">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                {{ ttsError }}
            </p>
        </div>
    </section>
    <section
        v-else-if="presentation === 'reading-intro'"
        class="relative overflow-hidden rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30 lg:p-8"
        :class="isSpeaking ? 'ring-2 ring-primary/20' : ''"
    >
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-blue-400/5 blur-3xl" />
        
        <div class="grid justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
        </div>
        <div class="relative mt-6 rounded-[24px] border border-slate-200/60 bg-slate-50/80 p-5 shadow-sm xl:mt-8">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/60 pb-4">
                <div>
                    <p class="text-base font-black uppercase tracking-widest text-primary xl:text-lg">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-0.5 text-sm font-bold text-slate-400">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-primary/5 px-4 py-2 text-sm font-black text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        {{ stateLabel }}
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-10 place-items-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <VolumeX v-if="isMuted" class="size-5" />
                        <Volume2 v-else class="size-5" />
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-10 place-items-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-5" />
                    </button>
                </div>
            </div>
            <div class="relative mt-5">
                <p class="text-base font-bold leading-relaxed text-slate-800 xl:text-lg">
                    {{ displayMessage }}
                </p>
                <p v-if="ttsError" class="mt-2 text-xs font-bold text-slate-500">
                    {{ ttsError }}
                </p>
            </div>
        </div>
    </section>
    <section
        v-else-if="presentation === 'assessment-task'"
        class="relative overflow-hidden rounded-[32px] border border-slate-200/80 bg-white p-6 shadow-xl shadow-slate-200/30"
        :class="isSpeaking ? 'ring-2 ring-primary/20' : ''"
    >
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-primary/5 blur-3xl" />
        
        <div class="relative grid justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
        </div>
        <div class="relative mt-4">
            <div class="flex flex-col items-center gap-3 md:flex-row md:justify-between">
                <div class="text-center md:text-left">
                    <p class="text-[15px] font-black uppercase tracking-widest text-primary">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-0.5 text-[12px] font-bold tracking-wide text-slate-400">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[12px] font-black text-emerald-600 ring-1 ring-emerald-200/60">
                        <Volume2 class="size-3.5" />
                        {{ stateLabel }}
                    </span>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-8 place-items-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        <VolumeX v-if="isMuted" class="size-4" />
                        <Volume2 v-else class="size-4" />
                    </button>
                </div>
            </div>
            <div class="mt-4 rounded-[20px] border border-slate-200/60 bg-slate-50/80 p-4 shadow-sm">
                <p class="text-[15px] font-bold leading-relaxed text-slate-800">
                    {{ displayMessage }}
                </p>
            </div>
        </div>
    </section>
    <section v-else class="agent-speaker-panel grid justify-items-center gap-2 rounded-[32px] border border-slate-200/80 bg-white shadow-xl shadow-slate-200/30 transition" :class="[compact ? 'p-4 lg:p-5' : 'p-5 lg:p-6 xl:p-8', isSpeaking ? 'ring-2 ring-primary/20' : '']">
        <AgentSpeakerTTS
            v-if="ttsEnabled && !voiceLoading"
            :key="ttsKey"
            :agent-type="agentType"
            :message="displayMessage"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            :audio-url="naturalAudioUrl"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="grid w-full justify-items-center">
            <div class="agent-media-box">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="agent-media-content"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
        </div>
        <div class="relative w-full rounded-[24px] border border-slate-200/60 bg-slate-50/80 p-5 shadow-sm xl:p-6" :class="compact ? 'p-4' : 'p-5 xl:p-6'">
            <span class="absolute left-1/2 top-0 size-5 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-slate-200/60 bg-slate-50" aria-hidden="true" />
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200/60 pb-3 xl:pb-4">
                <div>
                    <p class="font-black uppercase tracking-widest text-primary" :class="compact ? 'text-[11px]' : 'text-[13px] xl:text-sm'">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-0.5 font-bold text-slate-400" :class="compact ? 'text-xs' : 'text-sm'">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-black text-blue-600 ring-1 ring-blue-200/60">{{ stateLabel }}</span>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-9 place-items-center rounded-full ring-1 ring-primary/10 transition"
                        :class="isMuted ? 'bg-slate-100 text-slate-400 hover:bg-primary/10 hover:text-primary' : 'bg-primary/5 text-primary hover:bg-primary hover:text-white'"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        <VolumeX v-if="isMuted" class="size-4" />
                        <Volume2 v-else class="size-4" />
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-9 place-items-center rounded-full bg-primary/5 text-primary ring-1 ring-primary/10 transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-4" />
                    </button>
                </div>
            </div>
            <p class="font-bold leading-relaxed text-slate-800" :class="compact ? 'mt-3 text-sm md:text-base' : 'mt-4 text-base xl:text-lg'">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-slate-500">
                {{ ttsError }}
            </p>
        </div>
    </section>
</template>

<style scoped>
.agent-media-box {
    position: relative;
    display: grid;
    place-items: end center;
    width: 21rem;
    height: 21rem;
    overflow: visible;
    border: 0;
    border-radius: 0;
    outline: 0;
    background: transparent;
    box-shadow: none;
    backdrop-filter: none;
}

.agent-media-content {
    width: 100%;
    height: 100%;
}

@media (max-width: 767px) {
    .agent-media-box {
        width: 15.75rem;
        height: 15.75rem;
    }
}
</style>
