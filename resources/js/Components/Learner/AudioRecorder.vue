<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { CheckCircle2, Mic, RotateCcw, Send, Square } from 'lucide-vue-next';
import { stopAllAgentAudioBeforeRecording } from '../../utils/stopAgentAudio';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    maxDurationSeconds: { type: Number, default: 60 },
    minDurationSeconds: { type: Number, default: 1 },
    required: { type: Boolean, default: false },
    label: { type: String, default: 'Voice recording' },
    compact: { type: Boolean, default: false },
    spacebarEnabled: { type: Boolean, default: true },
    cueDelayMs: { type: Number, default: 1400 },
    requireReviewBeforeSubmit: { type: Boolean, default: true },
    autoTranscribeOnStop: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    submitted: { type: Boolean, default: false },
    submitLabel: { type: String, default: 'Submit My Answer' },
    externalError: { type: String, default: '' },
});

const emit = defineEmits(['recorded', 'submit', 'cleared', 'error', 'stateChanged']);

const status = ref('ready');
const duration = ref(0);
const errorMessage = ref('');
const audioUrl = ref('');
const mediaRecorder = ref(null);
const stream = ref(null);
const chunks = ref([]);
const currentFile = ref(null);
const timer = ref(null);
const cueTimer = ref(null);
const recordingStartedAt = ref(null);
const canSpeak = ref(false);

const statusLabel = computed(() => {
    const labels = {
        ready: 'Ready',
        recording: "I'm listening",
        processing: 'Processing',
        saved: props.submitted ? 'Submitted' : 'Listen',
        retry: 'Retry',
        error: 'Needs permission',
    };

    return labels[status.value] ?? 'Ready';
});

const minDurationLabel = computed(() => `${props.minDurationSeconds} ${props.minDurationSeconds === 1 ? 'second' : 'seconds'}`);

const helperText = computed(() => {
    const messages = {
        ready: props.spacebarEnabled ? `Tap Start Recording or press Space. Record at least ${minDurationLabel.value}.` : `Tap Start Recording. Record at least ${minDurationLabel.value}.`,
        recording: canSpeak.value ? (props.spacebarEnabled ? 'Speak now. Press Space when finished.' : 'Speak now.') : 'Get ready. Speak when the cue changes.',
        processing: 'Saving your voice.',
        saved: props.submitted ? 'Your answer was submitted.' : 'Listen to your answer. If you are happy with it, click Submit.',
        retry: "Let's try recording again.",
        error: props.externalError || errorMessage.value || 'The microphone needs permission.',
    };

    return messages[status.value] ?? 'Tap to record.';
});

const formattedDuration = computed(() => {
    if (status.value === 'recording' && duration.value < props.minDurationSeconds) {
        return duration.value.toFixed(1);
    }

    return String(Math.round(duration.value));
});

const hasMinimumDuration = computed(() => duration.value >= props.minDurationSeconds);

const cueText = computed(() => {
    if (status.value !== 'recording') {
        return `Minimum ${minDurationLabel.value} for transcription.`;
    }

    return canSpeak.value
        ? 'Speak now'
        : 'Wait for cue';
});

const setStatus = (nextStatus) => {
    status.value = nextStatus;
    emit('stateChanged', nextStatus);
};

const clearTimer = () => {
    if (timer.value) {
        clearInterval(timer.value);
        timer.value = null;
    }

    if (cueTimer.value) {
        clearTimeout(cueTimer.value);
        cueTimer.value = null;
    }
};

const stopTracks = () => {
    stream.value?.getTracks()?.forEach((track) => track.stop());
    stream.value = null;
};

const clearRecording = () => {
    clearTimer();
    stopTracks();
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
    audioUrl.value = '';
    chunks.value = [];
    currentFile.value = null;
    duration.value = 0;
    recordingStartedAt.value = null;
    canSpeak.value = false;
    errorMessage.value = '';
    setStatus('ready');
    emit('cleared');
};

const isEditableTarget = (target) => {
    if (!target || !(target instanceof HTMLElement)) {
        return false;
    }

    const tagName = target.tagName?.toLowerCase();

    return target.isContentEditable || ['input', 'textarea', 'select', 'button'].includes(tagName);
};

const audioBufferToWavBlob = (audioBuffer) => {
    const channelCount = 1;
    const sampleRate = audioBuffer.sampleRate;
    const source = audioBuffer.getChannelData(0);
    const bytesPerSample = 2;
    const blockAlign = channelCount * bytesPerSample;
    const buffer = new ArrayBuffer(44 + source.length * bytesPerSample);
    const view = new DataView(buffer);

    const writeString = (offset, value) => {
        for (let index = 0; index < value.length; index += 1) {
            view.setUint8(offset + index, value.charCodeAt(index));
        }
    };

    writeString(0, 'RIFF');
    view.setUint32(4, 36 + source.length * bytesPerSample, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, channelCount, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * blockAlign, true);
    view.setUint16(32, blockAlign, true);
    view.setUint16(34, 16, true);
    writeString(36, 'data');
    view.setUint32(40, source.length * bytesPerSample, true);

    let offset = 44;
    for (let index = 0; index < source.length; index += 1, offset += 2) {
        const sample = Math.max(-1, Math.min(1, source[index]));
        view.setInt16(offset, sample < 0 ? sample * 0x8000 : sample * 0x7fff, true);
    }

    return new Blob([view], { type: 'audio/wav' });
};

const convertRecordingToWav = async (blob) => {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;

    if (!AudioContextClass) {
        return { blob, durationSeconds: duration.value, extension: 'webm' };
    }

    const context = new AudioContextClass();

    try {
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await context.decodeAudioData(arrayBuffer.slice(0));

        return {
            blob: audioBufferToWavBlob(audioBuffer),
            durationSeconds: audioBuffer.duration,
            extension: 'wav',
        };
    } finally {
        await context.close();
    }
};

const startRecording = async () => {
    if (props.disabled || props.submitting || props.submitted || status.value === 'recording') return;

    try {
        clearRecording();
        await stopAllAgentAudioBeforeRecording();
        stream.value = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
        mediaRecorder.value = new MediaRecorder(stream.value, mimeType ? { mimeType } : undefined);
        chunks.value = [];

        mediaRecorder.value.ondataavailable = (event) => {
            if (event.data?.size) {
                chunks.value.push(event.data);
            }
        };

        mediaRecorder.value.onstop = async () => {
            const durationSeconds = duration.value;
            clearTimer();
            setStatus('processing');
            const blob = new Blob(chunks.value, { type: mediaRecorder.value?.mimeType || 'audio/webm' });

            try {
                const converted = await convertRecordingToWav(blob);
                const file = new File([converted.blob], `readirect-recording-${Date.now()}.${converted.extension}`, { type: converted.blob.type });
                file.durationSeconds = converted.durationSeconds || durationSeconds;
                currentFile.value = file;
                audioUrl.value = URL.createObjectURL(converted.blob);
                stopTracks();
                setStatus('saved');
                emit('recorded', file);

                if (props.autoTranscribeOnStop) {
                    emit('submit', file);
                }
            } catch (error) {
                stopTracks();
                errorMessage.value = 'Could not prepare the recording for transcription. Please record again.';
                setStatus('error');
                emit('error', errorMessage.value);
            }
        };

        mediaRecorder.value.start();
        recordingStartedAt.value = performance.now();
        canSpeak.value = false;
        setStatus('recording');
        cueTimer.value = setTimeout(() => {
            canSpeak.value = true;
        }, props.cueDelayMs);
        timer.value = setInterval(() => {
            duration.value = recordingStartedAt.value
                ? Math.max(0, (performance.now() - recordingStartedAt.value) / 1000)
                : duration.value;
            if (hasMinimumDuration.value && errorMessage.value) {
                errorMessage.value = '';
            }
            if (duration.value >= props.maxDurationSeconds) {
                stopRecording();
            }
        }, 100);
    } catch (error) {
        errorMessage.value = error?.name === 'NotAllowedError'
            ? 'Please allow the microphone so you can record your answer.'
            : 'This browser cannot record audio. Please use a supported browser.';
        setStatus('error');
        emit('error', errorMessage.value);
    }
};

const stopRecording = () => {
    if (status.value === 'recording' && !hasMinimumDuration.value) {
        errorMessage.value = `Keep recording for at least ${minDurationLabel.value} so transcription can start.`;
        return;
    }

    if (mediaRecorder.value?.state === 'recording') {
        duration.value = recordingStartedAt.value
            ? Math.max(duration.value, (performance.now() - recordingStartedAt.value) / 1000)
            : duration.value;
        mediaRecorder.value.stop();
    }
};

const submitRecording = () => {
    if (!currentFile.value || props.submitting || props.submitted) {
        return;
    }

    emit('submit', currentFile.value);
};

const handleSpacebar = (event) => {
    if (!props.spacebarEnabled || props.disabled || props.submitting || props.submitted || event.code !== 'Space' || event.repeat || isEditableTarget(event.target)) {
        return;
    }

    if (status.value === 'processing') {
        return;
    }

    event.preventDefault();

    if (status.value === 'recording') {
        stopRecording();
        return;
    }

    startRecording();
};

onMounted(() => {
    window.addEventListener('keydown', handleSpacebar);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleSpacebar);
    clearTimer();
    stopTracks();
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
});
</script>

<template>
    <div
        class="learner-audio-recorder rounded-3xl border border-primary/15 bg-primaryLight/50 shadow-sm shadow-primary/10"
        :class="compact ? 'p-3' : 'p-4'"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-black text-primaryDark">{{ label }}</p>
                <p class="text-xs font-bold text-muted">{{ helperText }}</p>
            </div>
            <span class="rounded-full bg-surface px-3 py-1 text-xs font-black text-primaryDark">{{ statusLabel }}</span>
        </div>

        <div
            class="mt-3 rounded-2xl border px-3 py-2 text-center text-sm font-black"
            :class="status === 'recording' && canSpeak ? 'border-success/30 bg-success/10 text-success' : 'border-primary/15 bg-surface text-primaryDark'"
            aria-live="polite"
        >
            {{ cueText }}
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button
                v-if="status !== 'recording'"
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-black text-white shadow-md shadow-primary/20 transition active:translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="disabled || submitting || submitted"
                @click="startRecording"
            >
                <Mic class="size-4" />
                {{ status === 'saved' ? 'Record again' : 'Start Recording' }}
            </button>
            <button
                v-else
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl bg-warning px-4 py-3 text-sm font-black text-white shadow-md shadow-warning/20 transition active:translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="!hasMinimumDuration"
                @click="stopRecording"
            >
                <Square class="size-4" />
                Stop
            </button>

            <button
                v-if="audioUrl && !submitted"
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl border-2 border-border bg-surface px-4 py-3 text-sm font-black text-primaryDark transition hover:border-primary"
                :disabled="submitting"
                @click="clearRecording"
            >
                <RotateCcw class="size-4" />
                Try Again
            </button>

            <div class="flex h-8 min-w-32 flex-1 items-end gap-1 rounded-2xl bg-surface px-3 py-2">
                <span
                    v-for="bar in 8"
                    :key="bar"
                    class="w-full rounded-full bg-primary/40"
                    :class="status === 'recording' ? 'animate-pulse' : ''"
                    :style="{ height: status === 'recording' ? `${20 + ((bar * 11 + duration * 7) % 55)}%` : `${18 + (bar % 4) * 8}%` }"
                />
            </div>

            <span class="w-14 text-right text-sm font-black text-muted">{{ formattedDuration }}s</span>
        </div>

        <p v-if="(errorMessage || externalError) && (status === 'recording' || status === 'error')" class="mt-2 text-xs font-black text-warning">
            {{ externalError || errorMessage }}
        </p>

        <p
            v-if="spacebarEnabled"
            class="mt-3 rounded-2xl bg-surface px-3 py-2 text-xs font-black text-primaryDark"
        >
            Press <span class="rounded-lg border border-border bg-white px-2 py-1 text-[11px] font-black text-primary">Space</span>
            to {{ status === 'recording' ? `stop after the ${minDurationLabel} minimum` : 'record' }}.
        </p>

        <div
            v-if="audioUrl && requireReviewBeforeSubmit"
            class="learner-audio-review-card mt-3 rounded-[24px] border-2 border-primary/25 bg-white p-4 shadow-md shadow-primary/10"
            aria-live="polite"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-lg font-black text-text">{{ submitted ? 'Answer submitted' : 'Your audio' }}</p>
                </div>
                <CheckCircle2 v-if="submitted" class="size-8 text-success" />
            </div>
            <audio class="mt-3 w-full" controls :src="audioUrl" :disabled="submitting" />
            <button
                v-if="!submitted"
                type="button"
                class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-success px-5 py-3 text-base font-black text-white shadow-md shadow-success/20 transition active:translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="submitting || !currentFile"
                @click="submitRecording"
            >
                <Send class="size-5" />
                {{ submitting ? 'Checking your answer...' : submitLabel }}
            </button>
        </div>
        <audio v-else-if="audioUrl" class="mt-3 w-full" controls :src="audioUrl" />
        <p v-if="required && !audioUrl" class="mt-2 text-xs font-bold text-muted">Please record your answer before continuing.</p>
    </div>
</template>
