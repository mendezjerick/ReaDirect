<script setup>
import { computed, ref, watch } from 'vue';
import { RotateCcw, Volume2, VolumeX } from 'lucide-vue-next';
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
});

const agents = {
    assessment: { label: 'Assessment Agent', initials: 'AA', base: '/assets/agents/assessment' },
    coach_feedback: { label: 'Coach + Feedback Agent', initials: 'CF', base: '/assets/agents/coach_feedback' },
    evaluator: { label: 'Evaluator / Recommendation Agent', initials: 'ER', base: '/assets/agents/evaluator' },
};

const displayMode = ref('requested');
const isSpeaking = ref(false);
const ttsError = ref('');
const ttsKey = ref(0);

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
const animationClass = computed(() => `agent-animate-${effectiveState.value}`);

watch(() => [props.agentType, effectiveState.value], () => {
    displayMode.value = 'requested';
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

const handleSpeakingStart = () => {
    ttsError.value = '';
    isSpeaking.value = true;
};

const handleSpeakingEnd = () => {
    isSpeaking.value = false;
};

const handleTtsError = (message) => {
    ttsError.value = message ? 'Voice is unavailable, but you can read the message here.' : '';
    isSpeaking.value = false;
};
</script>

<template>
    <section class="agent-speaker-panel grid gap-3 rounded-[24px] border border-border bg-surface shadow-xl shadow-primary/10 transition md:items-center" :class="[compact ? 'p-2.5 md:grid-cols-[86px_1fr] lg:grid-cols-1' : 'p-3 md:grid-cols-[132px_1fr] lg:grid-cols-1', isSpeaking ? 'ring-2 ring-primary/25' : '']">
        <AgentSpeakerTTS
            v-if="ttsEnabled"
            :key="ttsKey"
            :agent-type="agentType"
            :message="message"
            :mute="isMuted"
            :volume="volume"
            :rate="rate"
            :pitch="pitch"
            @speaking-start="handleSpeakingStart"
            @speaking-end="handleSpeakingEnd"
            @error="handleTtsError"
        />
        <div class="grid justify-items-center">
            <div class="grid place-items-end overflow-hidden rounded-[20px] bg-primary-light transition" :class="[compact ? 'h-24 w-20 md:h-24 md:w-20 lg:h-36 lg:w-32' : 'h-36 w-32 md:h-40 md:w-36 lg:h-52 lg:w-44', isSpeaking ? 'shadow-lg shadow-primary/25' : '']">
                <video
                    v-if="isVideoAsset"
                    :key="imageSrc"
                    class="h-full w-full object-contain"
                    :class="animationClass"
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
                    :class="animationClass"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center bg-primary font-black text-white" :class="[animationClass, compact ? 'text-2xl' : 'text-4xl']">
                    {{ agent.initials }}
                </div>
            </div>
        </div>
        <div class="relative rounded-[22px] border-2 border-primary-light bg-background shadow-sm" :class="compact ? 'p-3 lg:p-4' : 'p-4'">
            <span class="absolute left-1/2 top-0 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l-2 border-t-2 border-primary-light bg-background md:left-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 lg:left-1/2 lg:top-0 lg:-translate-x-1/2 lg:-translate-y-1/2" aria-hidden="true" />
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-black uppercase text-primary" :class="compact ? 'text-xs' : 'text-sm'">{{ displayTitle }}</p>
                    <p v-if="subtitle" class="mt-1 text-sm font-bold text-muted">{{ subtitle }}</p>
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
                {{ message }}
            </p>
            <p v-if="ttsError" class="mt-2 text-xs font-bold text-muted">
                {{ ttsError }}
            </p>
        </div>
    </section>
</template>
