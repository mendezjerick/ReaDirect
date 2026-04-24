<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    maxDurationSeconds: { type: Number, default: 60 },
    required: { type: Boolean, default: false },
    label: { type: String, default: 'Voice recording' },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['recorded', 'cleared', 'error', 'stateChanged']);

const status = ref('ready');
const duration = ref(0);
const errorMessage = ref('');
const audioUrl = ref('');
const mediaRecorder = ref(null);
const stream = ref(null);
const chunks = ref([]);
const timer = ref(null);

const statusLabel = computed(() => {
    const labels = {
        ready: 'Ready',
        recording: "I'm listening",
        processing: 'Processing',
        saved: 'Saved',
        retry: 'Retry',
        error: 'Needs permission',
    };

    return labels[status.value] ?? 'Ready';
});

const helperText = computed(() => {
    const messages = {
        ready: 'Tap to record.',
        recording: "I'm listening.",
        processing: 'Saving your voice.',
        saved: 'Great, your voice was saved.',
        retry: "Let's try recording again.",
        error: errorMessage.value || 'The microphone needs permission.',
    };

    return messages[status.value] ?? 'Tap to record.';
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
    duration.value = 0;
    errorMessage.value = '';
    setStatus('ready');
    emit('cleared');
};

const startRecording = async () => {
    if (props.disabled || status.value === 'recording') return;

    try {
        clearRecording();
        stream.value = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '';
        mediaRecorder.value = new MediaRecorder(stream.value, mimeType ? { mimeType } : undefined);
        chunks.value = [];

        mediaRecorder.value.ondataavailable = (event) => {
            if (event.data?.size) {
                chunks.value.push(event.data);
            }
        };

        mediaRecorder.value.onstop = () => {
            clearTimer();
            setStatus('processing');
            const blob = new Blob(chunks.value, { type: mediaRecorder.value?.mimeType || 'audio/webm' });
            const file = new File([blob], `readirect-recording-${Date.now()}.webm`, { type: blob.type });
            file.durationSeconds = duration.value;
            audioUrl.value = URL.createObjectURL(blob);
            stopTracks();
            setStatus('saved');
            emit('recorded', file);
        };

        mediaRecorder.value.start();
        setStatus('recording');
        timer.value = setInterval(() => {
            duration.value += 1;
            if (duration.value >= props.maxDurationSeconds) {
                stopRecording();
            }
        }, 1000);
    } catch (error) {
        errorMessage.value = 'The microphone needs permission.';
        setStatus('error');
        emit('error', errorMessage.value);
    }
};

const stopRecording = () => {
    if (mediaRecorder.value?.state === 'recording') {
        mediaRecorder.value.stop();
    }
};

onBeforeUnmount(() => {
    clearTimer();
    stopTracks();
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
});
</script>

<template>
    <div
        class="rounded-3xl border border-primary/15 bg-primaryLight/50 shadow-sm shadow-primary/10"
        :class="compact ? 'p-3' : 'p-4'"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-black text-primaryDark">{{ label }}</p>
                <p class="text-xs font-bold text-muted">{{ helperText }}</p>
            </div>
            <span class="rounded-full bg-surface px-3 py-1 text-xs font-black text-primaryDark">{{ statusLabel }}</span>
        </div>

        <div class="mt-3 flex items-center gap-3">
            <button
                v-if="status !== 'recording'"
                type="button"
                class="rounded-2xl bg-primary px-4 py-3 text-sm font-black text-white shadow-md shadow-primary/20 transition active:translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="disabled"
                @click="startRecording"
            >
                {{ status === 'saved' ? 'Record again' : 'Record' }}
            </button>
            <button
                v-else
                type="button"
                class="rounded-2xl bg-warning px-4 py-3 text-sm font-black text-white shadow-md shadow-warning/20 transition active:translate-y-0.5"
                @click="stopRecording"
            >
                Stop
            </button>

            <button
                v-if="audioUrl"
                type="button"
                class="rounded-2xl border-2 border-border bg-surface px-4 py-3 text-sm font-black text-primaryDark transition hover:border-primary"
                @click="clearRecording"
            >
                Retry
            </button>

            <div class="flex h-8 flex-1 items-end gap-1 rounded-2xl bg-surface px-3 py-2">
                <span
                    v-for="bar in 8"
                    :key="bar"
                    class="w-full rounded-full bg-primary/40"
                    :class="status === 'recording' ? 'animate-pulse' : ''"
                    :style="{ height: status === 'recording' ? `${20 + ((bar * 11 + duration * 7) % 55)}%` : `${18 + (bar % 4) * 8}%` }"
                />
            </div>

            <span class="w-12 text-right text-sm font-black text-muted">{{ duration }}s</span>
        </div>

        <audio v-if="audioUrl" class="mt-3 w-full" controls :src="audioUrl" />
        <p v-if="required && !audioUrl" class="mt-2 text-xs font-bold text-muted">Recording is optional when a typed transcript is provided.</p>
    </div>
</template>
