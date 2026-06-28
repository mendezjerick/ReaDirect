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
    intent: { type: String, default: '' },
    lineKey: { type: String, default: '' },
    presentation: { type: String, default: 'default' },
    allowCongrats: { type: Boolean, default: false },
});

const emit = defineEmits(['interaction-ended', 'speaking-start', 'speaking-end']);

const agents = {
    assessment: {
        label: 'Miss Vivian',
        role: 'Assessment Guide',
        intro: "Hi, I'm Miss Vivian. I'll guide you through this activity, so listen carefully and take your time.",
    },
    coach_feedback: {
        label: 'Miss Ciel',
        role: 'Reading Coach',
        intro: "Hi, I'm Miss Ciel. I'll read with you today, and we'll take each word slowly together.",
    },
    evaluator: {
        label: 'Miss Estelle',
        role: 'Results Guide',
        intro: "Hi, I'm Miss Estelle. I'll help you look at your results in a calm and simple way.",
    },
    evaluator_recommendation: {
        label: 'Miss Estelle',
        role: 'Results Guide',
        intro: "Hi, I'm Miss Estelle. I'll help you look at your results in a calm and simple way.",
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
    const hasTalkVideo = [
        'coach_feedback',
        'assessment',
        'evaluator',
        'evaluator_recommendation',
    ].includes(props.agentType);
    const passiveStates = ['idle', 'listening', 'speaking', 'neutral', 'none', 'ready'];

    if (hasTalkVideo && isSpeaking.value && passiveStates.includes(requestedState)) {
        return 'talk';
    }

    return requestedState === 'speaking' ? 'idle' : requestedState;
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
                intent: props.intent || undefined,
                line_key: props.lineKey || undefined,
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
    emit('speaking-start');
};

const handleSpeakingEnd = () => {
    isSpeaking.value = false;
    emit('speaking-end');
};

const handleTtsError = (message) => {
    if (!message) {
        ttsError.value = '';
        return;
    }

    if (message.toLowerCase().includes('autoplay')) {
        ttsError.value = '';
        isSpeaking.value = false;
        emit('speaking-end');
        return;
    }

    ttsError.value = 'Voice is unavailable, but you can read the message here.';
    isSpeaking.value = false;
    emit('speaking-end');
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
    () => [props.agentType, displayMessage.value, props.ttsEnabled, props.intent, props.lineKey],
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
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-accent/10 blur-3xl" />
        
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
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-accent/10 blur-3xl" />

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
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-accent/10 blur-3xl" />
        
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
        class="grid gap-0 rounded-[24px] bg-surface p-3 shadow-xl shadow-primary/10 sm:p-4"
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
            <div class="relative flex aspect-square w-32 max-w-full items-end justify-center sm:w-36 xl:w-40">
                <AgentVideoPlayer
                    :agent="agentType"
                    :action="mediaState"
                    :alt="displayTitle"
                    :allow-congrats="allowCongrats"
                    class="h-full w-full object-contain object-bottom"
                    @interaction-ended="emit('interaction-ended', $event)"
                />
            </div>
        </div>
        <div class="relative mt-2 rounded-[20px] border border-primary/20 bg-surface p-3 shadow-sm shadow-primary/10 sm:mt-3 sm:p-4 xl:rounded-[24px]">
            <span class="absolute left-1/2 top-0 size-6 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-primary/20 bg-surface" aria-hidden="true" />
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="grid size-9 place-items-center rounded-full bg-primary-light text-primary">
                        <GraduationCap class="size-5" />
                    </span>
                    <div>
                        <p class="text-base font-black uppercase text-primary xl:text-lg">{{ displayTitle }}</p>
                        <p v-if="displaySubtitle" class="text-xs font-black leading-none text-muted">{{ displaySubtitle }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-0">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-l-full rounded-r-none bg-primary-light px-2.5 py-1.5 text-[11px] font-black uppercase tracking-wider text-primary transition hover:bg-primary hover:text-white xl:px-3 xl:text-xs"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        {{ stateLabel }}
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-8 place-items-center rounded-r-full bg-primary-light text-primary transition hover:bg-primary hover:text-white xl:size-9"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <VolumeX v-if="isMuted" class="size-4" />
                        <Volume2 v-else class="size-4" />
                    </button>
                </div>
            </div>
            <p class="mt-3 text-base font-black leading-snug text-text xl:mt-4 xl:text-lg">
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
        <div class="pointer-events-none absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-accent/10 blur-3xl" />
        
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
        v-else-if="presentation === 'assessment-horizontal'"
        class="assessment-agent-strip"
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

        <div class="assessment-agent-card">
            <div class="assessment-agent-card-face">
                <div class="assessment-agent-square">
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
        </div>

        <div class="assessment-agent-dialogue">
            <div class="assessment-agent-dialogue-face">
                <div class="assessment-agent-dialogue-copy">
                    <p class="assessment-agent-dialogue-name">{{ displayTitle }}</p>
                    <p class="assessment-agent-dialogue-text">
                        {{ displayMessage }}
                    </p>
                    <p v-if="ttsError" class="assessment-agent-dialogue-error">
                        {{ ttsError }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="assessment-agent-audio-button grid size-9 place-items-center rounded-full transition"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        <VolumeX v-if="isMuted" class="size-4" />
                        <Volume2 v-else class="size-4" />
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="assessment-agent-audio-button grid size-9 place-items-center rounded-full transition"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-4" />
                    </button>
                </div>
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
    <section v-else class="agent-speaker-panel grid justify-items-center gap-2 rounded-[32px] border border-slate-200/80 bg-white shadow-xl shadow-slate-200/30 transition" :class="[compact ? 'p-4 lg:p-5' : 'p-4 sm:p-5 lg:p-6 xl:p-8', isSpeaking ? 'ring-2 ring-primary/20' : '']">
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
                    <span class="rounded-full bg-primary-light px-2.5 py-1 text-[11px] font-black text-primary ring-1 ring-primary/15">{{ stateLabel }}</span>
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
    max-width: min(100%, 21rem);
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

.assessment-agent-strip {
    display: grid;
    grid-template-columns: var(--assessment-agent-row, clamp(7.5rem, 17dvh, 11.25rem)) minmax(0, 1fr);
    align-items: stretch;
    gap: clamp(0.8rem, 1.4vw, 1.15rem);
    height: var(--assessment-agent-row, clamp(7.5rem, 17dvh, 11.25rem));
    min-height: 0;
    overflow: visible;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    transition: box-shadow 150ms ease;
}

.assessment-agent-card {
    display: block;
    min-height: 0;
    height: 100%;
    aspect-ratio: 1 / 1;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: 22px;
    background: var(--rd-story-surface);
    padding: 7px 7px 10px;
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
}

.assessment-agent-card-face {
    width: 100%;
    height: 100%;
    min-height: 0;
    overflow: hidden;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 15px;
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.assessment-agent-square {
    position: relative;
    display: grid;
    width: 100%;
    height: 100%;
    min-height: 0;
    place-items: end center;
    overflow: hidden;
    border: 0;
    border-radius: inherit;
    background: transparent;
}

.assessment-agent-dialogue {
    position: relative;
    display: grid;
    height: var(--assessment-agent-row, clamp(7.5rem, 17dvh, 11.25rem));
    min-width: 0;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: var(--rd-radius-frame);
    background: var(--rd-story-surface);
    padding: 10px 12px 14px;
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
}

.assessment-agent-dialogue::before {
    content: '';
    position: absolute;
    left: -0.9rem;
    top: 50%;
    width: 1.7rem;
    height: 1.7rem;
    border-left: 2px solid var(--rd-frame-border);
    border-bottom: 2px solid var(--rd-frame-border);
    background: var(--rd-story-surface);
    transform: translateY(-50%) rotate(45deg);
    box-shadow: -3px 3px 0 rgba(112, 84, 44, 0.08);
}

.assessment-agent-dialogue-face {
    display: flex;
    min-width: 0;
    min-height: 0;
    height: 100%;
    align-items: center;
    gap: 1rem;
    overflow: hidden;
    border: 1.5px solid var(--rd-face-border);
    border-radius: var(--rd-radius-face);
    background: var(--rd-face-surface);
    padding: clamp(0.62rem, 1.35dvh, 1rem) clamp(0.72rem, 1.25vw, 1.05rem);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.assessment-agent-dialogue-copy {
    display: grid;
    min-width: 0;
    flex: 1 1 auto;
    align-content: center;
    gap: clamp(0.18rem, 0.5dvh, 0.35rem);
}

.assessment-agent-dialogue-name {
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: clamp(0.9rem, 1.8dvh, 1.15rem);
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: 0;
    color: var(--rd-primary-orange);
}

.assessment-agent-dialogue-text {
    margin: 0;
    overflow: hidden;
    font-size: clamp(1rem, 2.1dvh, 1.25rem);
    font-weight: 900;
    line-height: 1.18;
    letter-spacing: 0;
    color: var(--rd-text-main);
}

.assessment-agent-dialogue-error {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--rd-text-muted);
}

.assessment-agent-audio-button {
    border: 2px solid var(--rd-story-border-soft);
    background: var(--rd-story-surface);
    color: var(--rd-text-main);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.18), 0 8px 14px rgba(54, 83, 101, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.assessment-agent-audio-button:hover {
    background: var(--rd-gold);
    color: var(--rd-text-main);
}

.assessment-agent-audio-button:active {
    transform: translateY(3px);
    box-shadow: 0 1px 0 rgba(111, 101, 52, 0.18), 0 4px 10px rgba(35, 55, 70, 0.08);
}

.assessment-agent-square :deep(.agent-media-player),
.assessment-agent-square :deep(.agent-media-layer) {
    width: 100%;
    height: 100%;
}

.assessment-agent-square :deep(.agent-media-layer) {
    object-fit: cover;
    object-position: center bottom;
}

@media (max-width: 767px) {
    .agent-media-box {
        width: 11rem;
        height: 11rem;
    }
}
</style>
