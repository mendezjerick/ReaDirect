import { computed, onBeforeUnmount, ref } from 'vue';

export const AUTOMATIC_CIEL_LISTENING_STATES = Object.freeze({
    IDLE: 'idle',
    REQUESTING_PERMISSION: 'requesting_permission',
    LISTENING: 'listening',
    RECORDING_SPEECH: 'recording_speech',
    SUBMITTING: 'submitting',
    PROCESSING: 'processing',
    CIEL_SPEAKING: 'ciel_speaking',
    TEACHING_MODE: 'teaching_mode',
    WAITING_FOR_RETRY: 'waiting_for_retry',
    COMPLETED: 'completed',
    ERROR: 'error',
});

export const AUTOMATIC_CIEL_LISTENING_MODE = 'automatic_ciel';
export const MANUAL_LISTENING_MODE = 'manual';

export const AUTOMATIC_CIEL_LISTENING_DEFAULTS = Object.freeze({
    speechThreshold: 0.035,
    silenceThreshold: 0.018,
    minimumSpeechDurationMs: 650,
    silenceDurationBeforeSubmitMs: 1000,
    maximumChunkDurationMs: 10000,
    cooldownAfterSubmitMs: 700,
    resumeAfterRetryMs: 1100,
});

const supportedMimeType = () => {
    if (typeof MediaRecorder === 'undefined') return '';

    return [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
    ].find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
};

const createId = (prefix) => {
    const random = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

    return `${prefix}-${random}`;
};

const userMessageForError = (error) => {
    const name = String(error?.name ?? '');

    if (['NotAllowedError', 'SecurityError', 'PermissionDeniedError'].includes(name)) {
        return 'Ciel needs microphone permission before listening. You can use Manual Recording Mode instead.';
    }

    if (['NotFoundError', 'DevicesNotFoundError'].includes(name)) {
        return 'Ciel cannot find a microphone. You can use Manual Recording Mode instead.';
    }

    if (name === 'NotReadableError') {
        return 'The microphone is busy right now. Close other recording apps or use Manual Recording Mode.';
    }

    return error?.message || 'Ciel could not listen safely. You can use Manual Recording Mode instead.';
};

export const automaticCielListeningIsSupported = () => {
    if (typeof window === 'undefined') return false;

    return Boolean(
        navigator?.mediaDevices?.getUserMedia
        && typeof MediaRecorder !== 'undefined'
        && (window.AudioContext || window.webkitAudioContext)
    );
};

export function useAutomaticCielListeningSession(callbacks = {}, config = {}) {
    const options = { ...AUTOMATIC_CIEL_LISTENING_DEFAULTS, ...config };
    const state = ref(AUTOMATIC_CIEL_LISTENING_STATES.IDLE);
    const errorMessage = ref('');
    const automaticSessionId = ref(null);
    const activeChunkId = ref(null);
    const isActive = ref(false);
    const isPaused = ref(false);
    const volumeLevel = ref(0);

    let stream = null;
    let audioContext = null;
    let analyser = null;
    let sourceNode = null;
    let sampleBuffer = null;
    let animationFrame = null;
    let mediaRecorder = null;
    let recorderChunks = [];
    let recordingStartedAt = 0;
    let speechStartedAt = 0;
    let lastSpeechAt = 0;
    let cooldownUntil = 0;
    let chunkCounter = 0;
    let ignoreRecorderStop = false;
    const submittedChunkIds = new Set();

    const currentStateLabel = computed(() => {
        const labels = {
            [AUTOMATIC_CIEL_LISTENING_STATES.IDLE]: 'Ready',
            [AUTOMATIC_CIEL_LISTENING_STATES.REQUESTING_PERMISSION]: 'Asking permission',
            [AUTOMATIC_CIEL_LISTENING_STATES.LISTENING]: 'Listening',
            [AUTOMATIC_CIEL_LISTENING_STATES.RECORDING_SPEECH]: 'Reading heard',
            [AUTOMATIC_CIEL_LISTENING_STATES.SUBMITTING]: 'Sending recording',
            [AUTOMATIC_CIEL_LISTENING_STATES.PROCESSING]: 'Ciel is checking',
            [AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING]: 'Ciel is speaking',
            [AUTOMATIC_CIEL_LISTENING_STATES.TEACHING_MODE]: 'Teaching mode',
            [AUTOMATIC_CIEL_LISTENING_STATES.WAITING_FOR_RETRY]: 'Try again',
            [AUTOMATIC_CIEL_LISTENING_STATES.COMPLETED]: 'Completed',
            [AUTOMATIC_CIEL_LISTENING_STATES.ERROR]: 'Needs help',
        };

        return labels[state.value] ?? 'Ready';
    });

    const isRecordingSpeech = computed(() => state.value === AUTOMATIC_CIEL_LISTENING_STATES.RECORDING_SPEECH);

    const setState = (nextState) => {
        state.value = nextState;
        callbacks.onStateChange?.(nextState);
    };

    const fail = (error) => {
        errorMessage.value = userMessageForError(error);
        setState(AUTOMATIC_CIEL_LISTENING_STATES.ERROR);
        callbacks.onError?.(errorMessage.value, error);
    };

    const stopMonitor = () => {
        if (animationFrame !== null && typeof window !== 'undefined') {
            window.cancelAnimationFrame(animationFrame);
        }

        animationFrame = null;
    };

    const cleanupAudioGraph = () => {
        stopMonitor();

        try {
            sourceNode?.disconnect();
        } catch {
            // No-op: disconnect may throw if the node is already detached.
        }

        sourceNode = null;
        analyser = null;
        sampleBuffer = null;

        if (audioContext) {
            audioContext.close().catch(() => {});
        }

        audioContext = null;
    };

    const stopStream = () => {
        stream?.getTracks?.().forEach((track) => track.stop());
        stream = null;
    };

    const resetRecorder = () => {
        mediaRecorder = null;
        recorderChunks = [];
        recordingStartedAt = 0;
        speechStartedAt = 0;
        lastSpeechAt = 0;
        ignoreRecorderStop = false;
        activeChunkId.value = null;
    };

    const discardActiveRecording = () => {
        if (!mediaRecorder) return;

        ignoreRecorderStop = true;

        try {
            if (mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                return;
            }
        } catch {
            // Recorder cleanup continues below.
        }

        resetRecorder();
    };

    const stopSession = (nextState = AUTOMATIC_CIEL_LISTENING_STATES.IDLE) => {
        isActive.value = false;
        isPaused.value = false;
        discardActiveRecording();
        cleanupAudioGraph();
        stopStream();
        automaticSessionId.value = null;
        cooldownUntil = 0;
        volumeLevel.value = 0;
        setState(nextState);
    };

    const setupAudioGraph = async () => {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        audioContext = new AudioContextClass();

        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }

        analyser = audioContext.createAnalyser();
        analyser.fftSize = 1024;
        analyser.smoothingTimeConstant = 0.75;
        sampleBuffer = new Uint8Array(analyser.fftSize);
        sourceNode = audioContext.createMediaStreamSource(stream);
        sourceNode.connect(analyser);
    };

    const readRms = () => {
        if (!analyser || !sampleBuffer) return 0;

        analyser.getByteTimeDomainData(sampleBuffer);

        let sum = 0;
        for (let index = 0; index < sampleBuffer.length; index += 1) {
            const centered = (sampleBuffer[index] - 128) / 128;
            sum += centered * centered;
        }

        const rms = Math.sqrt(sum / sampleBuffer.length);
        volumeLevel.value = Number(rms.toFixed(4));

        return rms;
    };

    const submitRecording = async (blob, durationMs) => {
        if (!automaticSessionId.value || !isActive.value) return null;

        const chunkId = createId(`chunk-${chunkCounter += 1}`);
        activeChunkId.value = chunkId;

        if (submittedChunkIds.has(chunkId)) {
            return { duplicate: true };
        }

        submittedChunkIds.add(chunkId);

        const durationSeconds = Number((durationMs / 1000).toFixed(3));
        const extension = blob.type.includes('ogg') ? 'ogg' : 'webm';
        const file = new File([blob], `${chunkId}.${extension}`, { type: blob.type || 'audio/webm' });
        file.durationSeconds = durationSeconds;
        file.audioMetadata = {
            total_duration_seconds: durationSeconds,
            speech_duration_seconds: durationSeconds,
            leading_silence_seconds: 0,
            trailing_silence_seconds: Number((options.silenceDurationBeforeSubmitMs / 1000).toFixed(3)),
            silence_ratio: 0,
            speech_ratio: 1,
            was_trimmed: false,
        };

        setState(AUTOMATIC_CIEL_LISTENING_STATES.SUBMITTING);
        cooldownUntil = Date.now() + options.cooldownAfterSubmitMs;

        try {
            setState(AUTOMATIC_CIEL_LISTENING_STATES.PROCESSING);
            const result = await callbacks.submitChunk?.({
                file,
                automatic_session_id: automaticSessionId.value,
                chunk_id: chunkId,
                session_mode: AUTOMATIC_CIEL_LISTENING_MODE,
                current_agent_state: state.value,
                duration_seconds: durationSeconds,
                audio_metadata: file.audioMetadata,
            });

            if (!isActive.value) {
                return result;
            }

            if (result?.complete === true) {
                stopSession(AUTOMATIC_CIEL_LISTENING_STATES.COMPLETED);
                return result;
            }

            if (result?.retry === true) {
                errorMessage.value = result.message || 'Try reading that again.';
                setState(AUTOMATIC_CIEL_LISTENING_STATES.WAITING_FOR_RETRY);
                cooldownUntil = Date.now() + options.resumeAfterRetryMs;
                return result;
            }

            if (result?.pause === true) {
                isPaused.value = true;
                setState(result.state || AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING);
                return result;
            }

            if (!isPaused.value) {
                setState(AUTOMATIC_CIEL_LISTENING_STATES.LISTENING);
            }

            return result;
        } catch (error) {
            submittedChunkIds.delete(chunkId);
            fail(error);
            return null;
        } finally {
            activeChunkId.value = null;
        }
    };

    const handleRecorderStop = async () => {
        const chunks = recorderChunks;
        const startedAt = recordingStartedAt;
        const durationMs = Date.now() - recordingStartedAt;
        const recorderType = mediaRecorder?.mimeType || supportedMimeType() || 'audio/webm';
        const shouldIgnore = ignoreRecorderStop;
        resetRecorder();

        if (shouldIgnore || !chunks.length || !isActive.value) {
            return;
        }

        if (durationMs < options.minimumSpeechDurationMs) {
            errorMessage.value = 'Try reading that again in a clear voice.';
            cooldownUntil = Date.now() + options.resumeAfterRetryMs;
            setState(AUTOMATIC_CIEL_LISTENING_STATES.WAITING_FOR_RETRY);
            return;
        }

        const blob = new Blob(chunks, { type: recorderType });
        await submitRecording(blob, Date.now() - startedAt);
    };

    const startRecording = () => {
        if (!stream || mediaRecorder || !isActive.value || isPaused.value) return;

        const mimeType = supportedMimeType();
        mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
        recorderChunks = [];
        recordingStartedAt = Date.now();
        speechStartedAt = recordingStartedAt;
        lastSpeechAt = recordingStartedAt;
        ignoreRecorderStop = false;

        mediaRecorder.ondataavailable = (event) => {
            if (event.data?.size) {
                recorderChunks.push(event.data);
            }
        };
        mediaRecorder.onstop = () => {
            handleRecorderStop();
        };
        mediaRecorder.onerror = (event) => {
            fail(event.error || new Error('Ciel had trouble recording. Please try again.'));
        };

        mediaRecorder.start();
        setState(AUTOMATIC_CIEL_LISTENING_STATES.RECORDING_SPEECH);
    };

    const finalizeRecording = () => {
        if (!mediaRecorder || mediaRecorder.state === 'inactive') return;

        try {
            mediaRecorder.stop();
        } catch (error) {
            fail(error);
        }
    };

    const monitor = () => {
        if (!isActive.value || !analyser || typeof window === 'undefined') {
            return;
        }

        animationFrame = window.requestAnimationFrame(monitor);

        if (isPaused.value || Date.now() < cooldownUntil) {
            return;
        }

        if (state.value === AUTOMATIC_CIEL_LISTENING_STATES.WAITING_FOR_RETRY) {
            setState(AUTOMATIC_CIEL_LISTENING_STATES.LISTENING);
        }

        if (state.value !== AUTOMATIC_CIEL_LISTENING_STATES.LISTENING
            && state.value !== AUTOMATIC_CIEL_LISTENING_STATES.RECORDING_SPEECH) {
            return;
        }

        const now = Date.now();
        const rms = readRms();

        if (!mediaRecorder) {
            if (rms >= options.speechThreshold) {
                startRecording();
            }
            return;
        }

        if (rms >= options.silenceThreshold) {
            lastSpeechAt = now;
        }

        const speechDuration = now - speechStartedAt;
        const silenceDuration = now - lastSpeechAt;
        const chunkDuration = now - recordingStartedAt;

        if (
            (speechDuration >= options.minimumSpeechDurationMs && silenceDuration >= options.silenceDurationBeforeSubmitMs)
            || chunkDuration >= options.maximumChunkDurationMs
        ) {
            finalizeRecording();
        }
    };

    const startSession = async () => {
        if (isActive.value) return true;

        if (!automaticCielListeningIsSupported()) {
            fail(new Error('This browser cannot run Automatic Ciel Listening Mode.'));
            return false;
        }

        errorMessage.value = '';
        setState(AUTOMATIC_CIEL_LISTENING_STATES.REQUESTING_PERMISSION);

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });
            automaticSessionId.value = createId('ciel-session');
            isActive.value = true;
            isPaused.value = false;
            submittedChunkIds.clear();
            chunkCounter = 0;
            await setupAudioGraph();
            setState(AUTOMATIC_CIEL_LISTENING_STATES.LISTENING);
            monitor();
            callbacks.onStarted?.(automaticSessionId.value);
            return true;
        } catch (error) {
            stopStream();
            cleanupAudioGraph();
            isActive.value = false;
            fail(error);
            return false;
        }
    };

    const pause = (nextState = AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING) => {
        if (!isActive.value) return;

        isPaused.value = true;
        discardActiveRecording();
        setState(nextState);
    };

    const resume = () => {
        if (!isActive.value || state.value === AUTOMATIC_CIEL_LISTENING_STATES.ERROR) return;

        isPaused.value = false;
        cooldownUntil = Date.now() + 350;
        setState(AUTOMATIC_CIEL_LISTENING_STATES.LISTENING);
    };

    const complete = () => {
        stopSession(AUTOMATIC_CIEL_LISTENING_STATES.COMPLETED);
    };

    onBeforeUnmount(() => {
        stopSession(AUTOMATIC_CIEL_LISTENING_STATES.IDLE);
    });

    return {
        state,
        currentStateLabel,
        errorMessage,
        automaticSessionId,
        activeChunkId,
        isActive: computed(() => isActive.value),
        isPaused: computed(() => isPaused.value),
        isRecordingSpeech,
        volumeLevel,
        thresholds: options,
        startSession,
        stopSession,
        pause,
        pauseForCiel: () => pause(AUTOMATIC_CIEL_LISTENING_STATES.CIEL_SPEAKING),
        pauseForTeaching: () => pause(AUTOMATIC_CIEL_LISTENING_STATES.TEACHING_MODE),
        resume,
        resumeAfterCiel: resume,
        complete,
    };
}
