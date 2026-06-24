<script setup>
import { computed } from 'vue';
import { Mic, RotateCcw, Square, Volume2 } from 'lucide-vue-next';
import {
    AUTOMATIC_CIEL_LISTENING_STATES,
    automaticCielListeningIsSupported,
    useAutomaticCielListeningSession,
} from '../../Composables/useAutomaticCielListeningSession';
import AssessmentCircleButton from './AssessmentCircleButton.vue';

const props = defineProps({
    activeItem: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
    submitChunk: { type: Function, required: true },
});

const emit = defineEmits(['error', 'fallback-manual', 'started', 'stopped', 'state-change']);

const session = useAutomaticCielListeningSession({
    submitChunk: (payload) => props.submitChunk(payload),
    onError: (message) => emit('error', message),
    onStarted: (sessionId) => emit('started', sessionId),
    onStateChange: (state) => emit('state-change', state),
});

const isSupported = computed(() => automaticCielListeningIsSupported());
const isBusy = computed(() => [
    AUTOMATIC_CIEL_LISTENING_STATES.REQUESTING_PERMISSION,
    AUTOMATIC_CIEL_LISTENING_STATES.SUBMITTING,
    AUTOMATIC_CIEL_LISTENING_STATES.PROCESSING,
].includes(session.state.value));
const canStart = computed(() => isSupported.value && !props.disabled && !session.isActive.value && !isBusy.value);
const canStop = computed(() => session.isActive.value || session.state.value === AUTOMATIC_CIEL_LISTENING_STATES.ERROR);
const isListeningVisual = computed(() => session.isActive.value || isBusy.value);
const buttonText = computed(() => isListeningVisual.value ? 'Listening...' : 'Tap to start');
const helperText = computed(() => {
    if (!isSupported.value) {
        return 'This browser cannot use automatic listening. Manual Recording Mode is still available.';
    }

    if (session.errorMessage.value) {
        return session.errorMessage.value;
    }

    const labels = {
        [AUTOMATIC_CIEL_LISTENING_STATES.IDLE]: 'Ciel listens only after you click Start.',
        [AUTOMATIC_CIEL_LISTENING_STATES.REQUESTING_PERMISSION]: 'Your browser is asking for microphone permission.',
        [AUTOMATIC_CIEL_LISTENING_STATES.LISTENING]: 'Read the word or sentence when you are ready.',
        [AUTOMATIC_CIEL_LISTENING_STATES.RECORDING_SPEECH]: 'Keep reading. Ciel is hearing your voice.',
        [AUTOMATIC_CIEL_LISTENING_STATES.SUBMITTING]: 'Ciel heard you and is sending the recording.',
        [AUTOMATIC_CIEL_LISTENING_STATES.PROCESSING]: 'Ciel is checking your reading.',
        [AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING]: 'Ciel is speaking now. Listening is paused.',
        [AUTOMATIC_CIEL_LISTENING_STATES.TEACHING_MODE]: 'Practice with Ciel first. Listening will wait.',
        [AUTOMATIC_CIEL_LISTENING_STATES.WAITING_FOR_RETRY]: 'Try reading that one again.',
        [AUTOMATIC_CIEL_LISTENING_STATES.COMPLETED]: 'This listening session is complete.',
        [AUTOMATIC_CIEL_LISTENING_STATES.ERROR]: 'Ciel stopped listening safely.',
    };

    return labels[session.state.value] ?? 'Ciel is ready.';
});

const start = async () => {
    await session.startSession();
};

const stop = () => {
    session.stopSession();
    emit('stopped');
};

const handlePrimary = () => {
    if (!session.isActive.value) {
        start();
    }
};

defineExpose({
    state: session.state,
    isActive: session.isActive,
    startSession: session.startSession,
    stopSession: session.stopSession,
    pauseForCiel: session.pauseForCiel,
    pauseForTeaching: session.pauseForTeaching,
    resumeAfterCiel: session.resumeAfterCiel,
    complete: session.complete,
});
</script>

<template>
    <section class="automatic-listening-recorder flex h-full min-h-0 flex-col items-center justify-center rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <AssessmentCircleButton
            :pulse="isListeningVisual && !session.isPaused.value"
            :disabled="!session.isActive.value && !canStart"
            :aria-label="buttonText"
            @click="handlePrimary"
        >
            <Volume2 v-if="isListeningVisual" class="size-11 stroke-[2.6]" />
            <Mic v-else class="size-11 stroke-[2.6]" />
        </AssessmentCircleButton>

        <p class="mt-4 text-center text-lg font-black text-slate-700" aria-live="polite">
            {{ buttonText }}
        </p>

        <div v-if="session.isActive.value" class="mt-3 h-1.5 w-full max-w-36 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full bg-emerald-400 transition-all"
                :style="{ width: `${Math.min(100, Math.round(session.volumeLevel.value * 1800))}%` }"
            />
        </div>

        <p
            v-if="session.errorMessage.value || !isSupported"
            class="mt-3 rounded-lg bg-orange-50 px-3 py-2 text-center text-xs font-black text-orange-600 ring-1 ring-orange-200/60"
        >
            {{ helperText }}
        </p>

        <div class="mt-3 flex flex-wrap justify-center gap-2">
            <button
                v-if="canStop && session.isActive.value"
                type="button"
                class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 shadow-sm transition hover:bg-slate-50"
                @click="stop"
            >
                <Square class="size-4" />
                Stop
            </button>
            <button
                type="button"
                class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-black text-slate-600 shadow-sm transition hover:bg-slate-50"
                @click="emit('fallback-manual')"
            >
                <RotateCcw class="size-4" />
                Manual
            </button>
        </div>
    </section>
</template>
