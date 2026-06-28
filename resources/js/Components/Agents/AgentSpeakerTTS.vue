<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    agentType: { type: String, required: true },
    message: { type: String, required: true },
    mute: { type: Boolean, default: false },
    volume: { type: Number, default: 1 },
    rate: { type: Number, default: 1 },
    pitch: { type: Number, default: 1 },
    audioUrl: { type: String, default: null },
    browserFallback: { type: Boolean, default: true },
});

const emit = defineEmits(['speakingStart', 'speakingEnd', 'error']);

const activeAudio = ref(null);

const cleanMessage = () => (props.message || '').replace(/\s+/g, ' ').trim();

const clamp = (value, min, max) => Math.min(Math.max(Number(value) || min, min), max);

const stopSpeaking = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (activeAudio.value) {
        activeAudio.value.pause();
        activeAudio.value.currentTime = 0;
        activeAudio.value = null;
    }

    emit('speakingEnd');
};

const playbackErrorMessage = (error = null) => {
    const name = String(error?.name ?? '').toLowerCase();
    const message = String(error?.message ?? '').toLowerCase();

    if (name.includes('notallowed') || message.includes('autoplay')) {
        return 'autoplay blocked';
    }

    return 'Agent voice audio could not be played.';
};

const speakWithTextFallback = (reason = 'Agent voice audio is unavailable.') => {
    emit('error', reason);
    emit('speakingEnd');
};

const speakWithAudio = async () => {
    const audio = new Audio(props.audioUrl);
    audio.dataset.readirectAgentAudio = 'true';
    audio.volume = clamp(props.volume, 0, 1);
    activeAudio.value = audio;

    audio.onplay = () => emit('speakingStart');
    audio.onended = () => {
        activeAudio.value = null;
        emit('speakingEnd');
    };
    audio.onerror = () => {
        activeAudio.value = null;
        speakWithTextFallback('Agent voice audio file could not be loaded.');
    };

    try {
        await audio.play();
    } catch (error) {
        activeAudio.value = null;
        speakWithTextFallback(playbackErrorMessage(error));
    }
};

const speak = async () => {
    const text = cleanMessage();

    if (!text || props.mute) {
        stopSpeaking();
        return;
    }

    if (props.audioUrl) {
        await speakWithAudio();
        return;
    }

    speakWithTextFallback();
};

watch(
    () => [props.message, props.agentType, props.mute, props.volume, props.rate, props.pitch, props.audioUrl],
    () => {
        stopSpeaking();

        if (!props.mute && typeof window !== 'undefined') {
            window.setTimeout(() => speak(), 80);
        }
    },
    { immediate: true },
);

onMounted(() => {
    window.addEventListener('readirect:stop-agent-audio', stopSpeaking);
    window.addEventListener('readirect:stop-agent-speech', stopSpeaking);
});

onBeforeUnmount(() => {
    window.removeEventListener('readirect:stop-agent-audio', stopSpeaking);
    window.removeEventListener('readirect:stop-agent-speech', stopSpeaking);
    stopSpeaking();
});
</script>

<template>
    <span class="sr-only" aria-live="polite">{{ message }}</span>
</template>
