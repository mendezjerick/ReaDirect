<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    agentType: { type: String, required: true },
    message: { type: String, required: true },
    mute: { type: Boolean, default: false },
    volume: { type: Number, default: 1 },
    rate: { type: Number, default: 1 },
    pitch: { type: Number, default: 1 },
    audioUrl: { type: String, default: null },
    browserSpeechAllowed: { type: Boolean, default: true },
});

const emit = defineEmits(['speakingStart', 'speakingEnd', 'error']);

const unsupportedLogged = ref(false);
const activeUtterance = ref(null);
const activeAudio = ref(null);

const agentVoiceConfig = {
    assessment: {
        preferredNames: ['Samantha', 'Susan', 'Zira', 'Joanna', 'Aria'],
        rate: 0.95,
        pitch: 1,
    },
    coach_feedback: {
        preferredNames: ['Victoria', 'Alice', 'Salli', 'Ava', 'Jenny'],
        rate: 1,
        pitch: 1.05,
    },
    evaluator: {
        preferredNames: ['Jenny', 'Kendra', 'Aria', 'Samantha'],
        rate: 1.05,
        pitch: 1.1,
    },
    evaluator_recommendation: {
        preferredNames: ['Jenny', 'Kendra', 'Aria', 'Samantha'],
        rate: 1.05,
        pitch: 1.1,
    },
};

const hasSpeechSupport = () => (
    typeof window !== 'undefined'
    && 'speechSynthesis' in window
    && 'SpeechSynthesisUtterance' in window
);

const cleanMessage = () => (props.message || '').replace(/\s+/g, ' ').trim();

const clamp = (value, min, max) => Math.min(Math.max(Number(value) || min, min), max);

const loadVoices = () => new Promise((resolve) => {
    const synth = window.speechSynthesis;
    const availableVoices = synth.getVoices();

    if (availableVoices.length > 0) {
        resolve(availableVoices);
        return;
    }

    const handleVoicesChanged = () => {
        synth.removeEventListener?.('voiceschanged', handleVoicesChanged);
        resolve(synth.getVoices());
    };

    synth.addEventListener?.('voiceschanged', handleVoicesChanged);
    window.setTimeout(() => {
        synth.removeEventListener?.('voiceschanged', handleVoicesChanged);
        resolve(synth.getVoices());
    }, 500);
});

const pickVoice = (voices) => {
    const config = agentVoiceConfig[props.agentType] ?? agentVoiceConfig.assessment;
    const englishVoices = voices.filter((voice) => voice.lang?.toLowerCase().startsWith('en'));
    const usEnglishVoices = englishVoices.filter((voice) => voice.lang?.toLowerCase().startsWith('en-us'));

    for (const preferredName of config.preferredNames) {
        const exactMatch = voices.find((voice) => voice.name === preferredName);

        if (exactMatch) {
            return exactMatch;
        }

        const partialMatch = voices.find((voice) => voice.name?.toLowerCase().includes(preferredName.toLowerCase()));

        if (partialMatch) {
            return partialMatch;
        }
    }

    return usEnglishVoices[0] ?? englishVoices[0] ?? voices[0] ?? null;
};

const stopSpeaking = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (activeAudio.value) {
        activeAudio.value.pause();
        activeAudio.value.currentTime = 0;
        activeAudio.value = null;
    }

    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
    }

    activeUtterance.value = null;
    emit('speakingEnd');
};

const speakWithBrowser = async (text) => {
    if (!props.browserSpeechAllowed) {
        emit('error', 'Browser speech fallback is disabled.');
        return;
    }

    if (!hasSpeechSupport()) {
        if (!unsupportedLogged.value) {
            console.info('Web Speech API not supported.');
            unsupportedLogged.value = true;
        }

        emit('error', 'Web Speech API not supported.');
        return;
    }

    const synth = window.speechSynthesis;
    const config = agentVoiceConfig[props.agentType] ?? agentVoiceConfig.assessment;

    synth.cancel();
    await nextTick();

    const voices = await loadVoices();
    const utterance = new SpeechSynthesisUtterance(text);

    utterance.voice = pickVoice(voices);
    utterance.volume = clamp(props.volume, 0, 1);
    utterance.rate = clamp(config.rate * props.rate, 0.8, 1.2);
    utterance.pitch = clamp(config.pitch * props.pitch, 0.8, 1.2);
    utterance.lang = utterance.voice?.lang ?? 'en-US';

    utterance.onstart = () => emit('speakingStart');
    utterance.onend = () => {
        activeUtterance.value = null;
        emit('speakingEnd');
    };
    utterance.onerror = (event) => {
        activeUtterance.value = null;

        if (!['canceled', 'interrupted'].includes(event.error)) {
            emit('error', 'Text-to-speech is not available right now.');
        }

        emit('speakingEnd');
    };

    activeUtterance.value = utterance;
    synth.speak(utterance);
};

const speakWithAudio = async (text) => {
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
        speakWithBrowser(text);
    };

    try {
        await audio.play();
    } catch {
        activeAudio.value = null;
        await speakWithBrowser(text);
    }
};

const speak = async () => {
    const text = cleanMessage();

    if (!text || props.mute) {
        stopSpeaking();
        return;
    }

    if (props.audioUrl) {
        await speakWithAudio(text);
        return;
    }

    await speakWithBrowser(text);
};

watch(
    () => [props.message, props.agentType, props.mute, props.volume, props.rate, props.pitch, props.audioUrl, props.browserSpeechAllowed],
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
