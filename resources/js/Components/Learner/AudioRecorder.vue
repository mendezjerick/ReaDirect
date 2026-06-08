<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CheckCircle2, Mic, RotateCcw, Send, Square } from 'lucide-vue-next';
import { stopAllAgentAudio, stopAllAgentAudioBeforeRecording } from '../../utils/stopAgentAudio';
import LearnerAudioPlayer from './LearnerAudioPlayer.vue';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    maxDurationSeconds: { type: Number, default: 60 },
    minDurationSeconds: { type: Number, default: 1 },
    required: { type: Boolean, default: false },
    label: { type: String, default: 'Voice recording' },
    compact: { type: Boolean, default: false },
    spacebarEnabled: { type: Boolean, default: true },
    cueDelayMs: { type: Number, default: 1400 },
    promptType: { type: String, default: 'word' },
    minSpeechDurationSeconds: { type: Number, default: null },
    maxLeadingSilenceSeconds: { type: Number, default: 1 },
    maxSilenceRatio: { type: Number, default: 0.85 },
    requireReviewBeforeSubmit: { type: Boolean, default: true },
    autoTranscribeOnStop: { type: Boolean, default: false },
    submitting: { type: Boolean, default: false },
    submitted: { type: Boolean, default: false },
    submitLabel: { type: String, default: 'Submit My Answer' },
    externalError: { type: String, default: '' },
    resetKey: { type: [String, Number], default: null },
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
const hasPendingRecording = computed(() => Boolean(currentFile.value));
const hasSubmittedRecording = computed(() => props.submitted && !hasPendingRecording.value);

const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth < 1024;
};


const statusLabel = computed(() => {
    const labels = {
        ready: 'Ready',
        recording: "I'm listening",
        processing: 'Processing',
        saved: hasSubmittedRecording.value ? 'Submitted' : 'Listen',
        retry: 'Retry',
        error: 'Needs permission',
    };

    return labels[status.value] ?? 'Ready';
});

const minDurationLabel = computed(() => `${props.minDurationSeconds} ${props.minDurationSeconds === 1 ? 'second' : 'seconds'}`);
const speechThresholdSeconds = computed(() => {
    if (props.minSpeechDurationSeconds !== null) {
        return props.minSpeechDurationSeconds;
    }

    return {
        letter: 0.12,
        word: 0.20,
        rhyme: 0.20,
        sentence: 0.75,
        paragraph: 1.5,
        passage: 1.5,
    }[props.promptType] ?? 0.35;
});

const isShortVoicePrompt = computed(() => ['letter', 'word', 'rhyme', 'rhyming_word'].includes(props.promptType));

const helperText = computed(() => {
    const messages = {
        ready: props.spacebarEnabled ? `Tap Start Recording or press Space. Record at least ${minDurationLabel.value}.` : `Tap Start Recording. Record at least ${minDurationLabel.value}.`,
        recording: canSpeak.value ? (props.spacebarEnabled ? 'Speak now. Press Space when finished.' : 'Speak now.') : 'Get ready. Speak when the cue changes.',
        processing: 'Saving your voice.',
        saved: hasSubmittedRecording.value ? 'Your answer was submitted.' : 'Listen to your answer. If you are happy with it, click Submit.',
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
const cueDelaySeconds = computed(() => Math.max(0, Number(props.cueDelayMs ?? 0) / 1000));
const shouldShowReviewSubmit = computed(() => props.requireReviewBeforeSubmit || !props.autoTranscribeOnStop);

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

const resetRecordingState = () => {
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
};

const clearRecording = () => {
    resetRecordingState();
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

const sliceAudioBuffer = (audioBuffer, startSecond, endSecond) => {
    const OfflineContextClass = window.OfflineAudioContext || window.webkitOfflineAudioContext;
    if (!OfflineContextClass) {
        return audioBuffer;
    }

    const sampleRate = audioBuffer.sampleRate;
    const start = Math.max(0, Math.floor(startSecond * sampleRate));
    const end = Math.min(audioBuffer.length, Math.ceil(endSecond * sampleRate));
    const length = Math.max(1, end - start);
    const offlineContext = new OfflineContextClass(1, length, sampleRate);
    const trimmed = offlineContext.createBuffer(1, length, sampleRate);
    const source = audioBuffer.getChannelData(0).slice(start, end);
    trimmed.copyToChannel(source, 0);

    return trimmed;
};

const resampleAudioBuffer = async (audioBuffer, targetSampleRate = 16000) => {
    if (audioBuffer.sampleRate === targetSampleRate) {
        return audioBuffer;
    }

    const OfflineContextClass = window.OfflineAudioContext || window.webkitOfflineAudioContext;
    if (!OfflineContextClass) {
        return audioBuffer;
    }

    const length = Math.max(1, Math.ceil(audioBuffer.duration * targetSampleRate));
    const offlineContext = new OfflineContextClass(1, length, targetSampleRate);
    const source = offlineContext.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(offlineContext.destination);
    source.start(0);

    return offlineContext.startRendering();
};

const padAudioBufferToDuration = (audioBuffer, minimumSeconds, audioContext = null) => {
    const targetDuration = Math.max(Number(minimumSeconds ?? 0), audioBuffer.duration);
    const targetLength = Math.ceil(targetDuration * audioBuffer.sampleRate);

    if (targetLength <= audioBuffer.length) {
        return audioBuffer;
    }

    if (!audioContext) {
        return audioBuffer;
    }

    const padded = audioContext.createBuffer(1, targetLength, audioBuffer.sampleRate);
    padded.copyToChannel(audioBuffer.getChannelData(0), 0);

    return padded;
};

const minimumUploadDurationSeconds = computed(() => Math.max(1, Number(props.minDurationSeconds ?? 1)));

const analyzeSpeech = (audioBuffer) => {
    const samples = audioBuffer.getChannelData(0);
    const sampleRate = audioBuffer.sampleRate;
    const frameSize = Math.max(1, Math.floor(sampleRate * 0.03));
    const minRms = isShortVoicePrompt.value ? 0.012 : 0.018;
    const speechFrames = [];

    for (let start = 0; start < samples.length; start += frameSize) {
        const end = Math.min(samples.length, start + frameSize);
        let sum = 0;

        for (let index = start; index < end; index += 1) {
            sum += samples[index] * samples[index];
        }

        const rms = Math.sqrt(sum / Math.max(1, end - start));
        if (rms >= minRms) {
            speechFrames.push([start, end]);
        }
    }

    const totalDuration = audioBuffer.duration;
    const firstSpeech = speechFrames[0]?.[0] ?? null;
    const lastSpeech = speechFrames[speechFrames.length - 1]?.[1] ?? null;
    const speechDuration = speechFrames.reduce((sum, [start, end]) => sum + ((end - start) / sampleRate), 0);
    const leadingSilence = firstSpeech === null ? totalDuration : firstSpeech / sampleRate;
    const trailingSilence = lastSpeech === null ? totalDuration : Math.max(0, totalDuration - (lastSpeech / sampleRate));
    const trimStart = firstSpeech === null ? 0 : Math.max(0, leadingSilence - 0.15);
    const trimEnd = lastSpeech === null ? totalDuration : Math.min(totalDuration, (lastSpeech / sampleRate) + 0.25);

    return {
        total_duration_seconds: Number(totalDuration.toFixed(3)),
        speech_duration_seconds: Number(speechDuration.toFixed(3)),
        leading_silence_seconds: Number(leadingSilence.toFixed(3)),
        trailing_silence_seconds: Number(trailingSilence.toFixed(3)),
        silence_ratio: Number((totalDuration > 0 ? Math.max(0, 1 - (speechDuration / totalDuration)) : 1).toFixed(3)),
        speech_ratio: Number((totalDuration > 0 ? speechDuration / totalDuration : 0).toFixed(3)),
        was_trimmed: trimStart > 0 || trimEnd < totalDuration,
        trim_start_seconds: trimStart,
        trim_end_seconds: trimEnd,
    };
};

const validationSilenceMetrics = (metadata) => {
    const cueSeconds = Math.min(cueDelaySeconds.value, Number(metadata.total_duration_seconds ?? 0));
    const totalAfterCue = Math.max(0.001, Number(metadata.total_duration_seconds ?? 0) - cueSeconds);
    const leadingSilenceAfterCue = Math.max(0, Number(metadata.leading_silence_seconds ?? 0) - cueSeconds);
    const speechDuration = Number(metadata.speech_duration_seconds ?? 0);

    return {
        leading_silence_seconds: leadingSilenceAfterCue,
        silence_ratio: Number(Math.max(0, 1 - (speechDuration / totalAfterCue)).toFixed(3)),
    };
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

        const metadata = analyzeSpeech(audioBuffer);
        const trimmedBuffer = metadata.was_trimmed
            ? sliceAudioBuffer(audioBuffer, metadata.trim_start_seconds, metadata.trim_end_seconds)
            : audioBuffer;
        const paddedBuffer = padAudioBufferToDuration(trimmedBuffer, minimumUploadDurationSeconds.value, context);
        const uploadBuffer = await resampleAudioBuffer(paddedBuffer, 16000);
        metadata.upload_duration_seconds = Number(uploadBuffer.duration.toFixed(3));
        metadata.was_padded_for_upload = uploadBuffer.duration > trimmedBuffer.duration;

        return {
            blob: audioBufferToWavBlob(uploadBuffer),
            durationSeconds: metadata.total_duration_seconds,
            extension: 'wav',
            audioMetadata: metadata,
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
                const metadata = converted.audioMetadata ?? {
                    total_duration_seconds: converted.durationSeconds || durationSeconds,
                    speech_duration_seconds: converted.durationSeconds || durationSeconds,
                    leading_silence_seconds: 0,
                    trailing_silence_seconds: 0,
                    silence_ratio: 0,
                    speech_ratio: 1,
                    was_trimmed: false,
                };

                if (metadata.speech_duration_seconds < speechThresholdSeconds.value) {
                    stopTracks();
                    errorMessage.value = `Please record again and speak for at least ${speechThresholdSeconds.value} seconds.`;
                    setStatus('retry');
                    emit('error', errorMessage.value);
                    return;
                }

                const validationMetrics = validationSilenceMetrics(metadata);
                metadata.validation_leading_silence_seconds = Number(validationMetrics.leading_silence_seconds.toFixed(3));
                metadata.validation_silence_ratio = validationMetrics.silence_ratio;
                metadata.cue_delay_seconds = Number(cueDelaySeconds.value.toFixed(3));

                if (validationMetrics.leading_silence_seconds > props.maxLeadingSilenceSeconds || validationMetrics.silence_ratio > props.maxSilenceRatio) {
                    stopTracks();
                    errorMessage.value = 'Please record again and start speaking after the cue.';
                    setStatus('retry');
                    emit('error', errorMessage.value);
                    return;
                }

                const file = new File([converted.blob], `readirect-recording-${Date.now()}.${converted.extension}`, { type: converted.blob.type });
                file.durationSeconds = converted.durationSeconds || durationSeconds;
                file.audioMetadata = metadata;
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
    if (!currentFile.value || props.submitting) {
        return;
    }

    emit('submit', currentFile.value);
};

const stopAgentAudioForPlayback = () => {
    stopAllAgentAudio();
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
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

watch(
    () => props.resetKey,
    () => {
        resetRecordingState();
    }
);

watch(
    () => props.submitted,
    (submitted) => {
        if (submitted && currentFile.value) {
            currentFile.value = null;
        }
    }
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleSpacebar);
    window.removeEventListener('resize', checkMobile);
    clearTimer();
    stopTracks();
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
});
</script>

<template>
    <div
        class="learner-audio-recorder rounded-[28px] border border-slate-200/80 bg-white shadow-lg shadow-slate-200/30"
        :class="compact ? 'p-4' : 'p-5 xl:p-6'"
    >
        <div class="learner-audio-control-panel">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-slate-800 xl:text-base">{{ label }}</p>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500 xl:text-sm">{{ helperText }}</p>
                </div>
                <span
                    class="shrink-0 rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-widest ring-1 xl:text-[12px]"
                    :class="status === 'recording' ? 'bg-red-50 text-red-600 ring-red-200/60' : status === 'saved' ? 'bg-emerald-50 text-emerald-600 ring-emerald-200/60' : 'bg-blue-50 text-blue-600 ring-blue-200/60'"
                >{{ statusLabel }}</span>
            </div>

            <div
                class="mt-4 rounded-[20px] border px-4 py-3 text-center text-sm font-black xl:text-base"
                :class="status === 'recording' && canSpeak ? 'border-emerald-200/60 bg-emerald-50/50 text-emerald-700' : 'border-slate-200/60 bg-slate-50/50 text-slate-700'"
                aria-live="polite"
            >
                {{ cueText }}
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button
                    v-if="status !== 'recording'"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-[18px] bg-gradient-to-br from-sky-400 to-blue-600 px-5 py-3 text-sm font-black text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-60 xl:text-base"
                    :disabled="disabled || submitting || submitted"
                    @click="startRecording"
                >
                    <Mic class="size-4" />
                    {{ status === 'saved' ? 'Record again' : 'Start Recording' }}
                </button>
                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-2 rounded-[18px] bg-gradient-to-br from-red-400 to-red-500 px-5 py-3 text-sm font-black text-white shadow-md shadow-red-500/20 ring-1 ring-white/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-60 xl:text-base"
                    :disabled="!hasMinimumDuration"
                    @click="stopRecording"
                >
                    <Square class="size-4" />
                    Stop
                </button>

                <button
                    v-if="audioUrl && hasPendingRecording"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-[18px] border-2 border-slate-200/80 bg-white px-5 py-3 text-sm font-black text-slate-600 transition-all hover:border-slate-300 hover:bg-slate-50 xl:text-base"
                    :disabled="submitting"
                    @click="clearRecording"
                >
                    <RotateCcw class="size-4" />
                    Try Again
                </button>

                <div class="flex h-8 min-w-32 flex-1 items-end gap-1 rounded-2xl bg-slate-50 px-3 py-2">
                    <span
                        v-for="bar in 8"
                        :key="bar"
                        class="w-full rounded-full bg-blue-300/50"
                        :class="status === 'recording' ? 'animate-pulse' : ''"
                        :style="{ height: status === 'recording' ? `${20 + ((bar * 11 + duration * 7) % 55)}%` : `${18 + (bar % 4) * 8}%` }"
                    />
                </div>

                <span class="w-14 text-right text-sm font-black text-slate-500">{{ formattedDuration }}s</span>
            </div>

            <p v-if="(errorMessage || externalError) && (status === 'recording' || status === 'retry' || status === 'error')" class="mt-3 rounded-[16px] bg-orange-50 px-4 py-3 text-xs font-black text-orange-600 ring-1 ring-orange-200/60">
                {{ externalError || errorMessage }}
            </p>

            <p
                v-if="spacebarEnabled"
                class="mt-4 rounded-[16px] border border-slate-200/60 bg-slate-50/50 px-4 py-2.5 text-xs font-bold text-slate-600"
            >
                Press <span class="rounded-lg border border-slate-200/80 bg-white px-2 py-1 text-[11px] font-black text-blue-600 shadow-sm">Space</span>
                to {{ status === 'recording' ? `stop after the ${minDurationLabel} minimum` : 'record' }}.
            </p>
        </div>

        <Teleport defer to="#teleport-audio-review" :disabled="isMobile">
            <div
                v-if="audioUrl && shouldShowReviewSubmit"
                :class="isMobile ? 'learner-audio-review-card mt-6 border-t border-slate-100 pt-5' : 'learner-audio-review-card rounded-[28px] border border-slate-200/80 bg-white p-4 shadow-lg shadow-slate-200/30 xl:p-5'"
                aria-live="polite"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-base font-black text-slate-800 xl:text-lg">{{ hasSubmittedRecording ? 'Answer submitted' : 'Your audio' }}</p>
                    </div>
                    <CheckCircle2 v-if="hasSubmittedRecording" class="size-6 text-emerald-500 xl:size-7" />
                </div>
                <LearnerAudioPlayer class="mt-3" :src="audioUrl" :disabled="submitting" @play="stopAgentAudioForPlayback" />
                <button
                    v-if="hasPendingRecording"
                    type="button"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-[20px] bg-gradient-to-br from-emerald-400 to-emerald-500 px-5 py-3.5 text-base font-black text-white shadow-lg shadow-emerald-500/25 ring-1 ring-white/20 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-xl active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 xl:text-lg"
                    :disabled="submitting || !currentFile"
                    @click="submitRecording"
                >
                    <Send class="size-5 xl:size-6" />
                    {{ submitting ? 'Checking your answer...' : submitLabel }}
                </button>
            </div>
        </Teleport>
        <Teleport defer to="#teleport-audio-review" :disabled="isMobile">
            <LearnerAudioPlayer v-if="!shouldShowReviewSubmit && audioUrl" class="mt-4" :src="audioUrl" @play="stopAgentAudioForPlayback" />
        </Teleport>
        <p v-if="required && !audioUrl" class="mt-3 text-xs font-bold text-slate-500">Please record your answer before continuing.</p>
    </div>
</template>
