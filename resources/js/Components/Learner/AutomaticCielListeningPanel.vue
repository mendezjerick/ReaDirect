<script setup>
import { computed } from 'vue';
import { AlertCircle, Loader2, Mic, MicOff, Radio, RotateCcw, Square } from 'lucide-vue-next';
import {
    AUTOMATIC_CIEL_LISTENING_STATES,
    automaticCielListeningIsSupported,
    useAutomaticCielListeningSession,
} from '../../Composables/useAutomaticCielListeningSession';

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
const stateTone = computed(() => {
    if (session.state.value === AUTOMATIC_CIEL_LISTENING_STATES.ERROR) return 'error';
    if ([AUTOMATIC_CIEL_LISTENING_STATES.LISTENING, AUTOMATIC_CIEL_LISTENING_STATES.RECORDING_SPEECH].includes(session.state.value)) return 'active';
    if ([AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING, AUTOMATIC_CIEL_LISTENING_STATES.TEACHING_MODE].includes(session.state.value)) return 'paused';

    return 'neutral';
});
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
    <section class="rounded-[28px] border border-blue-200/70 bg-blue-50/60 p-4 shadow-lg shadow-blue-100/40 xl:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <span class="grid size-12 shrink-0 place-items-center rounded-[18px] bg-white text-blue-600 shadow-sm ring-1 ring-blue-200/70">
                    <Radio v-if="session.isActive.value" class="size-6" />
                    <Mic v-else class="size-6" />
                </span>
                <div class="min-w-0">
                    <p class="text-base font-black text-slate-800 xl:text-lg">Automatic Ciel Listening</p>
                    <p class="mt-1 text-sm font-bold leading-snug text-slate-600">
                        Click Start before Ciel uses the microphone.
                    </p>
                </div>
            </div>
            <span
                class="rounded-full px-3 py-1.5 text-xs font-black"
                :class="{
                    'bg-emerald-100 text-emerald-700': stateTone === 'active',
                    'bg-amber-100 text-amber-700': stateTone === 'paused',
                    'bg-rose-100 text-rose-700': stateTone === 'error',
                    'bg-slate-100 text-slate-600': stateTone === 'neutral',
                }"
            >
                {{ session.currentStateLabel.value }}
            </span>
        </div>

        <div class="mt-4 rounded-[22px] bg-white/80 p-4 ring-1 ring-blue-100">
            <div class="flex items-center gap-3">
                <Loader2 v-if="isBusy" class="size-5 animate-spin text-blue-600" />
                <AlertCircle v-else-if="session.state.value === AUTOMATIC_CIEL_LISTENING_STATES.ERROR || !isSupported" class="size-5 text-rose-500" />
                <Mic v-else-if="session.state.value === AUTOMATIC_CIEL_LISTENING_STATES.LISTENING" class="size-5 text-emerald-600" />
                <MicOff v-else-if="session.isPaused.value" class="size-5 text-amber-600" />
                <Radio v-else class="size-5 text-blue-600" />
                <p class="text-sm font-black leading-snug text-slate-700 xl:text-base">{{ helperText }}</p>
            </div>
            <div v-if="session.isActive.value" class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full bg-emerald-400 transition-all"
                    :style="{ width: `${Math.min(100, Math.round(session.volumeLevel.value * 1800))}%` }"
                />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <button
                v-if="!session.isActive.value"
                type="button"
                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[18px] bg-blue-600 px-5 text-sm font-black text-white shadow-md shadow-blue-300/50 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!canStart"
                @click="start"
            >
                <Mic class="size-5" />
                Start Reading with Ciel
            </button>
            <button
                v-if="canStop"
                type="button"
                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[18px] border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 shadow-sm transition hover:bg-slate-50"
                @click="stop"
            >
                <Square class="size-4" />
                Stop Session
            </button>
            <button
                type="button"
                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[18px] border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 shadow-sm transition hover:bg-slate-50"
                @click="emit('fallback-manual')"
            >
                <RotateCcw class="size-4" />
                Use Manual Recording Mode
            </button>
        </div>
    </section>
</template>
