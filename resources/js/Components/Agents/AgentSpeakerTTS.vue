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

    if (window.speechSynthesis) {
        window.speechSynthesis.cancel();
    }

    emit('speakingEnd');
};

const speakWithTextFallback = () => {
    if (typeof window === 'undefined' || !window.speechSynthesis || !props.browserFallback) {
        emit('error', 'Voice is unavailable right now.');
        emit('speakingEnd');
        return;
    }

    try {
        const text = cleanMessage();
        if (!text) {
            emit('speakingEnd');
            return;
        }

        const utterance = new window.SpeechSynthesisUtterance(text);
        utterance.volume = clamp(props.volume, 0, 1);
        utterance.rate = clamp(props.rate, 0.1, 10);
        utterance.pitch = clamp(props.pitch, 0, 2);

        utterance.onstart = () => emit('speakingStart');
        utterance.onend = () => emit('speakingEnd');
        utterance.onerror = (e) => {
            // Ignore interruption errors
            if (e.error === 'interrupted' || e.error === 'canceled') return;
            if (e.error === 'not-allowed') {
                emit('error', 'autoplay blocked');
                emit('speakingEnd');
                return;
            }
            emit('error', 'Voice is unavailable right now.');
            emit('speakingEnd');
        };

        window.speechSynthesis.speak(utterance);
    } catch {
        emit('error', 'Voice is unavailable right now.');
        emit('speakingEnd');
    }
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
        speakWithTextFallback();
    };

    try {
        await audio.play();
    } catch (e) {
        activeAudio.value = null;
        if (e && e.name === 'NotAllowedError') {
            emit('error', 'autoplay blocked');
            emit('speakingEnd');
            return;
        }
        speakWithTextFallback();
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
