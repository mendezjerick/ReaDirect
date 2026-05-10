<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CheckCircle2, Mic, Pause, Play, RotateCcw, Send, Square, Volume2, VolumeX } from 'lucide-vue-next';
import { stopAllAgentAudio, stopAllAgentAudioBeforeRecording } from '../../utils/stopAgentAudio';

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
const spokenDuration = ref(0);
const playbackAudio = ref(null);
const playbackDuration = ref(0);
const playbackTime = ref(0);
const playbackMuted = ref(false);
const isPlaying = ref(false);
const voiceBars = [0.18, 0.34, 0.58, 0.42, 0.76, 1, 0.68, 0.52, 0.61, 0.86, 0.64, 0.37, 0.23];

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
    const activeDuration = status.value === 'recording' ? spokenDuration.value : duration.value;

    if (status.value === 'recording' && activeDuration < props.minDurationSeconds) {
        return activeDuration.toFixed(1);
    }

    return String(Math.round(activeDuration));
});

const hasMinimumDuration = computed(() => spokenDuration.value >= props.minDurationSeconds);

const cueText = computed(() => {
    if (status.value !== 'recording') {
        return `Minimum ${minDurationLabel.value} for transcription.`;
    }

    return canSpeak.value
        ? 'Speak now'
        : 'Wait for cue';
});

const formatPlaybackTime = (value) => {
    const safeValue = Number.isFinite(value) ? Math.max(0, value) : 0;
    const minutes = Math.floor(safeValue / 60);
    const seconds = Math.floor(safeValue % 60);

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
};

const playbackTimeLabel = computed(() => formatPlaybackTime(playbackTime.value));
const playbackDurationLabel = computed(() => formatPlaybackTime(playbackDuration.value || duration.value));
const playbackProgress = computed(() => {
    if (!playbackDuration.value) {
        return 0;
    }

    return Math.min(100, (playbackTime.value / playbackDuration.value) * 100);
});
const reviewTeleportTarget = '#learner-agent-followup';
const shouldTeleportReview = computed(() => props.compact && props.requireReviewBeforeSubmit);
const showCompactSubmittedState = computed(() => shouldTeleportReview.value && props.submitted);

const reviewWaveWidth = (intensity, index) => {
    const pulse = isPlaying.value ? Math.abs(Math.sin(playbackTime.value * 3.4 + index * 0.55)) * 0.3 : 0;

    return `${Math.round((intensity + pulse) * 100)}%`;
};

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
    if (playbackAudio.value) {
        playbackAudio.value.pause();
        playbackAudio.value.currentTime = 0;
    }
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
    audioUrl.value = '';
    chunks.value = [];
    currentFile.value = null;
    duration.value = 0;
    spokenDuration.value = 0;
    recordingStartedAt.value = null;
    canSpeak.value = false;
    errorMessage.value = '';
    playbackDuration.value = 0;
    playbackTime.value = 0;
    isPlaying.value = false;
    playbackMuted.value = false;
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

const trimSilenceFromAudioBuffer = (audioBuffer) => {
    const source = audioBuffer.getChannelData(0);
    const OfflineAudioContextClass = window.OfflineAudioContext || window.webkitOfflineAudioContext;

    if (!source?.length || !OfflineAudioContextClass) {
        return audioBuffer;
    }

    const silenceThreshold = 0.015;
    const leadingPaddingFrames = Math.round(audioBuffer.sampleRate * 0.12);
    const trailingPaddingFrames = Math.round(audioBuffer.sampleRate * 0.22);

    let startFrame = 0;
    while (startFrame < source.length && Math.abs(source[startFrame]) < silenceThreshold) {
        startFrame += 1;
    }

    let endFrame = source.length - 1;
    while (endFrame > startFrame && Math.abs(source[endFrame]) < silenceThreshold) {
        endFrame -= 1;
    }

    if (startFrame >= endFrame) {
        return audioBuffer;
    }

    const trimmedStart = Math.max(0, startFrame - leadingPaddingFrames);
    const trimmedEnd = Math.min(source.length, endFrame + trailingPaddingFrames + 1);
    const trimmedLength = trimmedEnd - trimmedStart;

    if (trimmedLength <= 0 || trimmedLength >= source.length) {
        return audioBuffer;
    }

    const context = new OfflineAudioContextClass(1, trimmedLength, audioBuffer.sampleRate);
    const trimmedBuffer = context.createBuffer(1, trimmedLength, audioBuffer.sampleRate);
    trimmedBuffer.copyToChannel(source.slice(trimmedStart, trimmedEnd), 0);

    return trimmedBuffer;
};

const convertRecordingToWav = async (blob) => {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    const OfflineAudioContextClass = window.OfflineAudioContext || window.webkitOfflineAudioContext;

    if (!AudioContextClass || !OfflineAudioContextClass) {
        return { blob, durationSeconds: duration.value, extension: 'webm' };
    }

    const context = new AudioContextClass();

    try {
        const arrayBuffer = await blob.arrayBuffer();
        const audioBuffer = await context.decodeAudioData(arrayBuffer.slice(0));
        const trimmedBuffer = trimSilenceFromAudioBuffer(audioBuffer);

        return {
            blob: audioBufferToWavBlob(trimmedBuffer),
            durationSeconds: trimmedBuffer.duration,
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
            if (recordingStartedAt.value) {
                const elapsedSeconds = Math.max(0, (performance.now() - recordingStartedAt.value) / 1000);
                duration.value = elapsedSeconds;
                spokenDuration.value = canSpeak.value
                    ? Math.max(0, elapsedSeconds - (props.cueDelayMs / 1000))
                    : 0;
            }
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
    if (recordingStartedAt.value) {
        const elapsedSeconds = Math.max(duration.value, (performance.now() - recordingStartedAt.value) / 1000);
        duration.value = elapsedSeconds;
        spokenDuration.value = canSpeak.value
            ? Math.max(0, elapsedSeconds - (props.cueDelayMs / 1000))
            : 0;
    }

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

const stopAgentAudioForPlayback = () => {
    stopAllAgentAudio();
};

const syncPlaybackState = () => {
    if (!playbackAudio.value) {
        return;
    }

    playbackTime.value = playbackAudio.value.currentTime || 0;
    playbackDuration.value = playbackAudio.value.duration || currentFile.value?.durationSeconds || duration.value || 0;
    playbackMuted.value = playbackAudio.value.muted;
};

const handlePlaybackLoaded = () => {
    syncPlaybackState();
};

const handlePlaybackTimeUpdate = () => {
    syncPlaybackState();
};

const handlePlaybackEnded = () => {
    isPlaying.value = false;
    if (playbackAudio.value) {
        playbackAudio.value.currentTime = 0;
    }
    playbackTime.value = 0;
};

const togglePlayback = async () => {
    if (!playbackAudio.value) {
        return;
    }

    stopAgentAudioForPlayback();

    if (playbackAudio.value.paused) {
        try {
            await playbackAudio.value.play();
            isPlaying.value = true;
            syncPlaybackState();
        } catch {
            isPlaying.value = false;
        }
        return;
    }

    playbackAudio.value.pause();
    isPlaying.value = false;
};

const togglePlaybackMute = () => {
    if (!playbackAudio.value) {
        return;
    }

    playbackAudio.value.muted = !playbackAudio.value.muted;
    playbackMuted.value = playbackAudio.value.muted;
};

watch(audioUrl, () => {
    playbackTime.value = 0;
    playbackDuration.value = currentFile.value?.durationSeconds || duration.value || 0;
    isPlaying.value = false;
    playbackMuted.value = false;
});

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
    if (playbackAudio.value) {
        playbackAudio.value.pause();
    }
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
            v-if="showCompactSubmittedState"
            class="mt-3 rounded-2xl border border-success/20 bg-success/10 px-3 py-3"
            aria-live="polite"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 class="size-5 text-success" />
                <p class="text-sm font-black text-success">Answer saved</p>
            </div>
            <p class="mt-2 text-xs font-bold text-success/90">Use the action button below to continue to the next step.</p>
        </div>
        <template v-else>
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
        </template>

        <Teleport v-if="audioUrl && requireReviewBeforeSubmit" defer :to="reviewTeleportTarget" :disabled="!shouldTeleportReview">
            <div
                class="learner-audio-review-card rounded-[24px] border-2 border-primary/25 bg-white p-4 shadow-md shadow-primary/10"
                :class="shouldTeleportReview ? 'learner-audio-review-card--rail' : 'mt-3'"
                aria-live="polite"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-primary/80">{{ submitted ? 'Answer submitted' : 'Your audio' }}</p>
                        <p class="text-xs font-bold text-muted">{{ submitted ? 'Saved successfully.' : 'Review your voice note before sending it.' }}</p>
                    </div>
                    <CheckCircle2 v-if="submitted" class="size-8 text-success" />
                </div>
                <div class="voice-message-shell mt-3 rounded-[22px] border border-primary/15 bg-primaryLight/60 p-3 shadow-sm shadow-primary/10">
                    <div class="voice-message-meta flex flex-wrap items-center justify-between gap-2 text-xs font-bold text-primaryDark/80">
                        <span>Voice preview</span>
                        <span>{{ playbackDurationLabel }}</span>
                    </div>
                    <div class="voice-message-player mt-2.5 flex items-center gap-2 rounded-[999px] border border-primary/10 bg-surface px-2 py-2 text-text shadow-sm">
                        <button
                            type="button"
                            class="voice-message-play inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-white shadow-md shadow-primary/25 transition hover:scale-[1.02]"
                            :aria-label="isPlaying ? 'Pause audio' : 'Play audio'"
                            @click="togglePlayback"
                        >
                            <Pause v-if="isPlaying" class="size-4" />
                            <Play v-else class="ml-0.5 size-4" />
                        </button>

                        <div class="min-w-0 flex-1">
                        <div class="voice-message-track relative flex h-9 items-center gap-1 overflow-hidden rounded-full px-2">
                            <div class="voice-message-track__progress" :style="{ width: `${playbackProgress}%` }" />
                            <span
                                v-for="(bar, index) in voiceBars"
                                    :key="index"
                                    class="voice-message-bar"
                                    :class="isPlaying ? 'is-playing' : ''"
                                    :style="{ height: `${22 + bar * 48}%`, width: reviewWaveWidth(bar, index) }"
                                />
                            </div>
                        </div>

                        <div class="voice-message-time shrink-0 text-right">
                            <p class="text-xl font-black leading-none text-primaryDark">{{ playbackTimeLabel }}</p>
                        </div>

                        <button
                            type="button"
                            class="voice-message-volume inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-primaryLight text-primary transition hover:bg-primary hover:text-white"
                            :aria-label="playbackMuted ? 'Unmute audio' : 'Mute audio'"
                            @click="togglePlaybackMute"
                        >
                            <VolumeX v-if="playbackMuted" class="size-4" />
                            <Volume2 v-else class="size-4" />
                        </button>
                    </div>
                    <audio
                        ref="playbackAudio"
                        class="sr-only"
                        :src="audioUrl"
                        :disabled="submitting"
                        @loadedmetadata="handlePlaybackLoaded"
                        @timeupdate="handlePlaybackTimeUpdate"
                        @ended="handlePlaybackEnded"
                        @play="isPlaying = true"
                        @pause="isPlaying = false"
                    />
                </div>
                <button
                    v-if="!submitted"
                    type="button"
                    class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-success px-4 py-2.5 text-sm font-black text-white shadow-md shadow-success/20 transition active:translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60"
                    :class="shouldTeleportReview ? 'learner-audio-review-submit' : ''"
                    :disabled="submitting || !currentFile"
                    @click="submitRecording"
                >
                    <Send class="size-5" />
                    {{ submitting ? 'Checking your answer...' : submitLabel }}
                </button>
            </div>
        </Teleport>
        <audio v-else-if="audioUrl" class="mt-3 w-full" controls :src="audioUrl" @play="stopAgentAudioForPlayback" />
        <p v-if="required && !audioUrl" class="mt-2 text-xs font-bold text-muted">Please record your answer before continuing.</p>
    </div>
</template>

<style scoped>
.voice-message-shell {
    position: relative;
    overflow: hidden;
}

.voice-message-shell::before {
    content: '';
    position: absolute;
    inset: auto auto -2.5rem -2.5rem;
    width: 8rem;
    height: 8rem;
    border-radius: 9999px;
    background: rgba(59, 130, 246, 0.08);
    filter: blur(8px);
}

.voice-message-player {
    position: relative;
    z-index: 1;
}

.learner-audio-review-card {
    container-type: inline-size;
}

.learner-audio-review-card--rail {
    display: grid;
    gap: 0.55rem;
    padding: 0.75rem;
}

.voice-message-track {
    background:
        linear-gradient(180deg, rgba(248, 250, 252, 1), rgba(239, 246, 255, 1));
}

.voice-message-track__progress {
    position: absolute;
    inset-block: 0.6rem;
    left: 0.5rem;
    border-radius: 9999px;
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.18));
}

.voice-message-bar {
    position: relative;
    z-index: 1;
    flex: 1 1 0;
    min-width: 0.22rem;
    max-width: 0.42rem;
    border-radius: 9999px;
    background: rgba(37, 99, 235, 0.45);
    transition: transform 180ms ease, opacity 180ms ease, background-color 180ms ease, width 180ms ease;
}

.voice-message-bar.is-playing {
    background: rgba(37, 99, 235, 0.95);
    opacity: 0.95;
}

@container (max-width: 26rem) {
    .learner-audio-review-card--rail {
        gap: 0.5rem;
        padding: 0.7rem;
    }

    .learner-audio-review-card--rail .voice-message-shell {
        margin-top: 0.45rem;
        padding: 0.65rem;
    }

    .learner-audio-review-card--rail .voice-message-player {
        padding: 0.5rem;
    }

    .learner-audio-review-card--rail .voice-message-track {
        height: 1.9rem;
    }

    .learner-audio-review-card--rail .voice-message-time p {
        font-size: 1.05rem;
    }

    .learner-audio-review-card--rail .voice-message-play {
        width: 2.15rem;
        height: 2.15rem;
    }

    .learner-audio-review-card--rail .voice-message-volume {
        width: 1.85rem;
        height: 1.85rem;
    }

    .learner-audio-review-card--rail .voice-message-bar {
        max-width: 0.28rem;
    }

    .voice-message-player {
        display: grid;
        grid-template-columns: auto 1fr auto;
        grid-template-areas:
            "play track volume"
            "play time volume";
        align-items: center;
        gap: 0.75rem;
    }

    .voice-message-play {
        grid-area: play;
    }

    .voice-message-track {
        grid-area: track;
    }

    .voice-message-time {
        grid-area: time;
        text-align: left;
    }

    .voice-message-volume {
        grid-area: volume;
    }

    .voice-message-time p {
        font-size: 1.4rem;
    }
}

.learner-audio-review-submit {
    margin-top: 0.1rem;
}
</style>
