<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { GraduationCap, RotateCcw, Volume2, VolumeX } from 'lucide-vue-next';
import AgentSpeakerTTS from '../Agents/AgentSpeakerTTS.vue';

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
});

const agents = {
    assessment: {
        label: 'Miss Vivian',
        role: 'Assessment Guide',
        initials: 'MV',
        base: '/assets/agents/assessment',
        intro: 'Hello! I am Miss Vivian. I will guide you through your reading assessment. Try your best and answer one step at a time.',
    },
    coach_feedback: {
        label: 'Miss Ciel',
        role: 'Reading Coach',
        initials: 'MC',
        base: '/assets/agents/coach_feedback',
        intro: 'Hi! I am Miss Ciel. I will help you practice reading. Mistakes are okay. I am here to guide you.',
    },
    evaluator: {
        label: 'Miss Estelle',
        role: 'Results Guide',
        initials: 'ME',
        base: '/assets/agents/evaluator',
        intro: 'Hello! I am Miss Estelle. I will help explain your results so you know what to do next.',
    },
    evaluator_recommendation: {
        label: 'Miss Estelle',
        role: 'Results Guide',
        initials: 'ME',
        base: '/assets/agents/evaluator',
        intro: 'Hello! I am Miss Estelle. I will help explain your results so you know what to do next.',
    },
};

const displayMode = ref('requested');
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
const prefersAssessmentWebmIdle = computed(() => props.agentType === 'assessment');
const requestedSrc = computed(() => {
    if (prefersAssessmentWebmIdle.value && effectiveState.value === 'idle') {
        return `${agent.value.base}/idle.webm`;
    }

    return `${agent.value.base}/${effectiveState.value}.png`;
});
const idleWebmSrc = computed(() => `${agent.value.base}/idle.webm`);
const idleSrc = computed(() => `${agent.value.base}/idle.png`);
const imageSrc = computed(() => {
    if (displayMode.value === 'requested') {
        return requestedSrc.value;
    }

    if (displayMode.value === 'idle_webm') {
        return idleWebmSrc.value;
    }

    return idleSrc.value;
});
const showPlaceholder = computed(() => displayMode.value === 'placeholder');
const isVideoAsset = computed(() => !showPlaceholder.value && imageSrc.value.endsWith('.webm'));
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
watch(() => [props.agentType, effectiveState.value], () => {
    displayMode.value = 'requested';
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

const handleImageError = () => {
    if (displayMode.value === 'requested' && prefersAssessmentWebmIdle.value && effectiveState.value !== 'idle') {
        displayMode.value = 'idle_webm';
        return;
    }

    if (displayMode.value === 'requested' && prefersAssessmentWebmIdle.value) {
        displayMode.value = 'idle';
        return;
    }

    if (displayMode.value === 'requested' && effectiveState.value !== 'idle') {
        displayMode.value = 'idle';
        return;
    }

    if (displayMode.value === 'idle_webm') {
        displayMode.value = 'idle';
        return;
    }

    displayMode.value = 'placeholder';
};

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
        class="relative overflow-hidden rounded-[32px] border-2 border-blue-200 bg-surface p-7 shadow-xl shadow-primary/10"
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
        <span class="absolute left-7 top-9 size-12 rounded-[18px] bg-blue-100 text-primary shadow-sm" aria-hidden="true">
            <span class="absolute left-3 top-4 size-2 rounded-full bg-primary/35" />
            <span class="absolute left-1/2 top-4 size-2 -translate-x-1/2 rounded-full bg-primary/35" />
            <span class="absolute right-3 top-4 size-2 rounded-full bg-primary/35" />
            <span class="absolute -bottom-2 right-2 size-5 rotate-45 rounded-sm bg-blue-100" />
        </span>
        <div class="grid justify-items-center">
            <div class="grid size-72 place-items-end overflow-hidden rounded-full border-4 border-blue-200 bg-blue-50 md:size-80 lg:size-[clamp(15rem,21vw,20rem)]">
                <video
                    v-if="isVideoAsset"
                    :key="imageSrc"
                    class="h-full w-full object-contain"
                    :aria-label="displayTitle"
                    autoplay
                    loop
                    muted
                    playsinline
                    @error="handleImageError"
                >
                    <source :src="imageSrc" type="video/webm">
                </video>
                <img
                    v-else-if="!showPlaceholder"
                    :src="imageSrc"
                    :alt="displayTitle"
                    class="h-full w-full object-contain"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center bg-primary text-5xl font-black text-white">
                    {{ agent.initials }}
                </div>
            </div>
            <p class="mt-5 text-center text-2xl font-black uppercase text-primary">{{ displayTitle }}</p>
            <div class="mt-2 flex flex-wrap justify-center gap-2">
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary-light px-4 py-2 text-sm font-black text-primary transition hover:bg-primary hover:text-white"
                    :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                    @click="toggleMute"
                >
                    <VolumeX v-if="isMuted" class="size-5" />
                    <Volume2 v-else class="size-5" />
                    {{ isMuted ? 'Muted' : stateLabel }}
                </button>
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-primary-light px-4 py-2 text-sm font-black text-primary transition hover:bg-primary hover:text-white"
                    aria-label="Replay agent message"
                    @click="replayMessage"
                >
                    <RotateCcw class="size-5" />
                    Replay
                </button>
            </div>
        </div>
        <div class="relative mt-5 rounded-[22px] border border-blue-100 bg-blue-50/70 p-6 shadow-md shadow-primary/10">
            <p class="text-xl font-black leading-relaxed text-text">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                {{ ttsError }}
            </p>
            <span class="absolute bottom-4 right-5 text-3xl font-black text-primary" aria-hidden="true">*</span>
        </div>
    </section>
    <section
        v-else-if="presentation === 'reading-results'"
        class="relative overflow-hidden rounded-[26px] border border-blue-100 bg-surface p-4 shadow-xl shadow-primary/10 xl:p-5"
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
        <span class="absolute left-10 top-24 text-3xl font-black text-blue-200" aria-hidden="true">*</span>
        <span class="absolute right-8 top-20 text-4xl font-black text-accent" aria-hidden="true">*</span>
        <div class="grid justify-items-center">
            <div class="grid h-60 w-full max-w-72 place-items-end overflow-hidden rounded-[22px] bg-blue-50 xl:h-72 xl:max-w-80">
                <video
                    v-if="isVideoAsset"
                    :key="imageSrc"
                    class="h-full w-full object-contain object-bottom"
                    :aria-label="displayTitle"
                    autoplay
                    loop
                    muted
                    playsinline
                    @error="handleImageError"
                >
                    <source :src="imageSrc" type="video/webm">
                </video>
                <img
                    v-else-if="!showPlaceholder"
                    :src="imageSrc"
                    :alt="displayTitle"
                    class="h-full w-full object-contain object-bottom"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center bg-primary text-5xl font-black text-white">
                    {{ agent.initials }}
                </div>
            </div>
        </div>
        <div class="relative mt-4 rounded-[18px] border border-blue-200 bg-surface p-4 shadow-sm shadow-primary/10 xl:mt-5 xl:p-5">
            <span class="absolute left-1/2 top-0 size-6 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-blue-200 bg-surface" aria-hidden="true" />
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
                <div>
                    <p class="text-lg font-black uppercase text-primary xl:text-xl">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-1 text-sm font-black text-muted xl:text-base">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-primary-light px-3 py-1.5 text-xs font-black text-primary transition hover:bg-primary hover:text-white xl:px-4 xl:py-2 xl:text-sm"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        {{ stateLabel }}
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                    class="grid size-9 place-items-center rounded-full border border-blue-100 bg-surface text-primary transition hover:bg-primary hover:text-white xl:size-10"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <VolumeX v-if="isMuted" class="size-5" />
                        <Volume2 v-else class="size-5" />
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                    class="grid size-9 place-items-center rounded-full border border-blue-100 bg-surface text-primary transition hover:bg-primary hover:text-white xl:size-10"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-5" />
                    </button>
                </div>
            </div>
            <div class="relative mt-4 rounded-[16px] border border-blue-200 bg-blue-50/70 p-4 xl:mt-5 xl:p-5">
                <span class="absolute left-1/2 top-0 size-5 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-blue-200 bg-blue-50" aria-hidden="true" />
                <p class="text-base font-black leading-relaxed text-text xl:text-lg">
                    {{ displayMessage }}
                </p>
                <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                    {{ ttsError }}
                </p>
            </div>
        </div>
    </section>
    <section
        v-else-if="presentation === 'summary'"
        class="relative overflow-hidden rounded-[28px] border border-blue-100 bg-surface p-4 shadow-xl shadow-primary/10 sm:p-5 2xl:p-6"
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
        <span class="absolute left-10 top-20 text-2xl font-black text-accent" aria-hidden="true">*</span>
        <span class="absolute right-16 top-24 text-3xl font-black text-blue-200" aria-hidden="true">*</span>
        <span class="absolute bottom-5 left-6 h-10 w-24 rounded-t-full bg-blue-100" aria-hidden="true" />
        <div class="grid justify-items-center">
            <div class="grid h-64 w-full max-w-[21rem] place-items-end overflow-hidden rounded-b-[16px] bg-transparent sm:h-72 lg:h-[clamp(17rem,32vh,21rem)]">
                <video
                    v-if="isVideoAsset"
                    :key="imageSrc"
                    class="h-full w-full object-contain object-bottom"
                    :aria-label="displayTitle"
                    autoplay
                    loop
                    muted
                    playsinline
                    @error="handleImageError"
                >
                    <source :src="imageSrc" type="video/webm">
                </video>
                <img
                    v-else-if="!showPlaceholder"
                    :src="imageSrc"
                    :alt="displayTitle"
                    class="h-full w-full object-contain object-bottom"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center rounded-[24px] bg-primary text-5xl font-black text-white">
                    {{ agent.initials }}
                </div>
            </div>
        </div>
        <div class="relative mt-2 rounded-[24px] bg-surface p-4 shadow-sm shadow-primary/10 sm:p-5">
            <div class="flex flex-wrap items-center justify-center gap-3">
                <p class="w-full text-center text-xl font-black uppercase text-primary sm:text-2xl">{{ displayTitle }}</p>
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="inline-flex min-w-0 items-center gap-2 rounded-full bg-primary-light px-3 py-2 text-sm font-black text-primary transition hover:bg-primary hover:text-white sm:px-4 sm:text-base"
                    :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                    @click="toggleMute"
                >
                    <VolumeX v-if="isMuted" class="size-5" />
                    <Volume2 v-else class="size-5" />
                    {{ stateLabel }}
                </button>
                <button
                    v-if="ttsEnabled || showAudioButton"
                    type="button"
                    class="grid size-10 place-items-center rounded-full bg-primary-light text-primary transition hover:bg-primary hover:text-white"
                    aria-label="Replay agent message"
                    @click="replayMessage"
                >
                    <RotateCcw class="size-5" />
                </button>
            </div>
            <div class="relative mt-5 rounded-[18px] border border-blue-200 bg-blue-50/70 p-4 shadow-sm shadow-primary/10 sm:p-5 2xl:p-6">
                <span class="absolute left-1/2 top-0 size-5 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-blue-200 bg-blue-50" aria-hidden="true" />
                <p class="text-base font-black leading-relaxed text-text sm:text-lg 2xl:text-xl">
                    {{ displayMessage }}
                </p>
                <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                    {{ ttsError }}
                </p>
            </div>
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
        <div class="relative grid min-h-56 place-items-end overflow-hidden rounded-[24px] border border-blue-100 bg-blue-50 px-4 pt-4 sm:min-h-72 sm:pt-5 xl:rounded-[28px]">
            <span class="absolute left-9 top-12 size-3 rounded-full bg-white" aria-hidden="true" />
            <span class="absolute left-20 top-24 text-3xl font-black text-blue-200" aria-hidden="true">*</span>
            <span class="absolute right-8 top-16 text-2xl font-black text-white" aria-hidden="true">*</span>
            <span class="absolute bottom-0 left-0 h-24 w-36 rounded-tr-full bg-white/80" aria-hidden="true" />
            <span class="absolute bottom-0 right-0 h-24 w-32 rounded-tl-full bg-blue-100/80" aria-hidden="true" />
            <video
                v-if="isVideoAsset"
                :key="imageSrc"
                class="relative z-10 h-56 w-full object-contain object-bottom sm:h-72 xl:h-80"
                :aria-label="displayTitle"
                autoplay
                loop
                muted
                playsinline
                @error="handleImageError"
            >
                <source :src="imageSrc" type="video/webm">
            </video>
            <img
                v-else-if="!showPlaceholder"
                :src="imageSrc"
                :alt="displayTitle"
                class="relative z-10 h-56 w-full object-contain object-bottom sm:h-72 xl:h-80"
                @error="handleImageError"
            >
            <div v-else class="relative z-10 grid size-56 place-items-center rounded-[24px] bg-primary text-5xl font-black text-white">
                {{ agent.initials }}
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
        class="grid gap-1"
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
        <div class="relative grid min-h-56 place-items-end overflow-hidden rounded-t-[28px] border border-blue-100 bg-surface px-5 pt-5 shadow-lg shadow-primary/10">
            <span class="absolute left-10 top-16 text-2xl font-black text-accent" aria-hidden="true">*</span>
            <span class="absolute right-8 top-10 text-3xl font-black text-blue-200" aria-hidden="true">*</span>
            <span class="absolute right-6 top-24 h-20 w-20 rounded-full bg-blue-100/70" aria-hidden="true" />
            <video
                v-if="isVideoAsset"
                :key="imageSrc"
                class="relative z-10 h-56 w-full object-contain object-bottom"
                :aria-label="displayTitle"
                autoplay
                loop
                muted
                playsinline
                @error="handleImageError"
            >
                <source :src="imageSrc" type="video/webm">
            </video>
            <img
                v-else-if="!showPlaceholder"
                :src="imageSrc"
                :alt="displayTitle"
                class="relative z-10 h-56 w-full object-contain object-bottom"
                @error="handleImageError"
            >
            <div v-else class="relative z-10 grid size-48 place-items-center rounded-[24px] bg-primary text-5xl font-black text-white">
                {{ agent.initials }}
            </div>
        </div>
        <div class="relative rounded-b-[28px] border border-blue-100 bg-surface p-5 shadow-lg shadow-primary/10">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-base font-black uppercase text-primary">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-1 text-sm font-black text-muted">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-0">
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-l-full rounded-r-none bg-primary-light px-4 py-2 text-sm font-black text-primary transition hover:bg-primary hover:text-white"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        {{ stateLabel }}
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-10 place-items-center rounded-r-full bg-primary-light text-primary transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <VolumeX v-if="isMuted" class="size-5" />
                        <Volume2 v-else class="size-5" />
                    </button>
                </div>
            </div>
            <p class="mt-5 text-xl font-black leading-relaxed text-text">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                {{ ttsError }}
            </p>
            <span class="absolute bottom-4 left-7 text-3xl font-black text-blue-100" aria-hidden="true">*</span>
        </div>
    </section>
    <section
        v-else-if="presentation === 'assessment-task'"
        class="relative overflow-hidden rounded-[28px] border border-blue-100 bg-surface p-5 shadow-xl shadow-primary/10"
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
        <span class="absolute left-12 top-16 size-2 rounded-full bg-blue-200" aria-hidden="true" />
        <span class="absolute right-14 top-12 size-3 rounded-full bg-accent/60" aria-hidden="true" />
        <span class="absolute right-8 top-24 size-2 rounded-full bg-blue-200" aria-hidden="true" />
        <div class="grid justify-items-center">
            <div class="grid h-52 w-56 place-items-end overflow-hidden rounded-full bg-blue-100/70 md:h-56 md:w-60 lg:h-[clamp(12rem,24vh,15rem)] lg:w-[clamp(13rem,17vw,16rem)]">
                <video
                    v-if="isVideoAsset"
                    :key="imageSrc"
                    class="h-full w-full object-contain"
                    :aria-label="displayTitle"
                    autoplay
                    loop
                    muted
                    playsinline
                    @error="handleImageError"
                >
                    <source :src="imageSrc" type="video/webm">
                </video>
                <img
                    v-else-if="!showPlaceholder"
                    :src="imageSrc"
                    :alt="displayTitle"
                    class="h-full w-full object-contain"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center bg-primary text-5xl font-black text-white">
                    {{ agent.initials }}
                </div>
            </div>
        </div>
        <div class="mt-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-2xl font-black text-primary">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-0.5 text-base font-black text-muted">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-success/10 px-4 py-2 text-sm font-black text-success">
                        <Volume2 class="size-4" />
                        {{ stateLabel }}
                    </span>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-10 place-items-center rounded-full border border-blue-100 bg-surface text-primary transition hover:bg-primary hover:text-white"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        <VolumeX v-if="isMuted" class="size-5" />
                        <Volume2 v-else class="size-5" />
                    </button>
                </div>
            </div>
            <div class="relative mt-4 rounded-[18px] bg-blue-50/80 p-5 shadow-sm shadow-primary/10">
                <span class="absolute left-1/2 top-0 size-5 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-blue-50/80" aria-hidden="true" />
                <p class="text-lg font-black leading-relaxed text-slate-950">
                    {{ displayMessage }}
                </p>
            </div>
        </div>
    </section>
    <section v-else class="agent-speaker-panel grid gap-3 rounded-[24px] border border-border bg-surface shadow-xl shadow-primary/10 transition md:items-center" :class="[compact ? 'p-2.5 md:grid-cols-[86px_1fr] lg:grid-cols-1 lg:p-[clamp(0.8rem,1vw,1.25rem)]' : 'p-3 md:grid-cols-[132px_1fr] lg:grid-cols-1 lg:p-[clamp(0.9rem,1.1vw,1.4rem)]', isSpeaking ? 'ring-2 ring-primary/25' : '']">
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
            <div class="grid place-items-end overflow-hidden rounded-[20px] bg-white transition" :class="[compact ? 'h-24 w-20 md:h-24 md:w-20 lg:h-[clamp(11rem,22vh,18rem)] lg:w-[clamp(9.5rem,15vw,15rem)]' : 'h-36 w-32 md:h-40 md:w-36 lg:h-[clamp(13rem,26vh,22rem)] lg:w-[clamp(11rem,18vw,18rem)]', isSpeaking ? 'shadow-lg shadow-primary/25' : '']">
                <video
                    v-if="isVideoAsset"
                    :key="imageSrc"
                    class="h-full w-full object-contain"
                    :aria-label="displayTitle"
                    autoplay
                    loop
                    muted
                    playsinline
                    @error="handleImageError"
                >
                    <source :src="imageSrc" type="video/webm">
                </video>
                <img
                    v-else-if="!showPlaceholder"
                    :src="imageSrc"
                    :alt="displayTitle"
                    class="h-full w-full object-contain"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center bg-primary font-black text-white" :class="compact ? 'text-2xl' : 'text-4xl'">
                    {{ agent.initials }}
                </div>
            </div>
        </div>
        <div class="relative rounded-[22px] border-2 border-primary-light bg-background shadow-sm" :class="compact ? 'p-3 lg:p-4' : 'p-4'">
            <span class="absolute left-1/2 top-0 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l-2 border-t-2 border-primary-light bg-background md:left-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 lg:left-1/2 lg:top-0 lg:-translate-x-1/2 lg:-translate-y-1/2" aria-hidden="true" />
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-black uppercase text-primary" :class="compact ? 'text-xs' : 'text-sm'">{{ displayTitle }}</p>
                    <p v-if="displaySubtitle" class="mt-1 text-sm font-bold text-muted">{{ displaySubtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-primary-light px-2 py-0.5 text-[10px] font-black text-primary">{{ stateLabel }}</span>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-9 place-items-center rounded-full transition"
                        :class="isMuted ? 'bg-border text-muted hover:bg-primary-light hover:text-primary' : 'bg-primary-light text-primary hover:bg-primary hover:text-white'"
                        :aria-label="isMuted ? 'Unmute agent voice' : 'Mute agent voice'"
                        @click="toggleMute"
                    >
                        <VolumeX v-if="isMuted" class="size-4" />
                        <Volume2 v-else class="size-4" />
                    </button>
                    <button
                        v-if="ttsEnabled || showAudioButton"
                        type="button"
                        class="grid size-9 place-items-center rounded-full bg-primary-light text-primary transition hover:bg-primary hover:text-white"
                        aria-label="Replay agent message"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-4" />
                    </button>
                </div>
            </div>
            <p class="font-black leading-snug text-text" :class="compact ? 'mt-2 text-sm md:text-base lg:text-[17px]' : 'mt-3 text-lg'">
                {{ displayMessage }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                {{ ttsError }}
            </p>
        </div>
    </section>
</template>
