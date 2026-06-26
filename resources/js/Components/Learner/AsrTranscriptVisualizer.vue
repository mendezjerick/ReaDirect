<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { MessageSquareText } from 'lucide-vue-next';
import { useAsrVisualization } from '../../Composables/useAsrVisualization';

const props = defineProps({
    visualizationEnabled: { type: Boolean, default: null },
    isProcessing: { type: Boolean, default: false },
    transcript: { type: String, default: '' },
    expectedText: { type: String, default: '' },
    asrResult: { type: Object, default: null },
    trace: { type: Object, default: null },
    error: { type: String, default: '' },
    placeholder: { type: String, default: 'Your words will appear here' },
    processingText: { type: String, default: 'Checking your recording...' },
    normalMode: { type: String, default: 'textarea' },
    boxClass: {
        type: [String, Array, Object],
        default: 'learner-transcript-box resize-none rounded-2xl border-2 border-border bg-background font-black text-text focus:border-primary focus:outline-none',
    },
    placeholderClass: { type: String, default: 'text-muted' },
    replayKey: { type: [String, Number], default: '' },
    playOnResult: { type: Boolean, default: false },
});

const { enabled } = useAsrVisualization();
const effectiveEnabled = computed(() => props.visualizationEnabled ?? enabled.value);
const visualizing = ref(false);
const stageTitle = ref('ASR Process');
const displayText = ref('');
const progress = ref(0);
const stageNumber = ref(0);
const totalStages = 8;
let runId = 0;
let timers = [];
const animatedResultKey = ref('');

const SCRAMBLES_PER_CHARACTER = 10;
const TICK_INTERVAL_MS = 45;
const scrambleCharacters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#@$%&*+=?';
const reduceMotion = typeof window !== 'undefined'
    && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;

const clearTimers = () => {
    timers.forEach((timer) => window.clearTimeout(timer));
    timers = [];
};

const sleep = (ms) => new Promise((resolve) => {
    const timer = window.setTimeout(() => {
        timers = timers.filter((item) => item !== timer);
        resolve();
    }, ms);
    timers.push(timer);
});

const asArray = (value) => (Array.isArray(value) ? value : []);
const firstPresent = (...values) => values.find((value) => value !== null && value !== undefined && String(value).trim() !== '');
const formatValue = (value) => {
    if (value === true) return 'true';
    if (value === false) return 'false';
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'number') return Number.isFinite(value) ? value.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') : String(value);
    return String(value ?? '').trim();
};

const resolvedTrace = computed(() => {
    const result = props.asrResult ?? {};

    return props.trace
        ?? result.trace
        ?? result.ai_response?.trace
        ?? result.debug_info?.trace
        ?? result.ai_response?.debug_info?.trace
        ?? result.debug_info?.asr?.trace
        ?? result.ai_response?.debug_info?.asr?.trace
        ?? {};
});

const resolvedTraceNotes = computed(() => {
    const result = props.asrResult ?? {};
    const notes = result.trace_notes
        ?? result.ai_response?.trace_notes
        ?? result.debug_info?.trace_notes
        ?? result.ai_response?.debug_info?.trace_notes
        ?? [];

    return asArray(notes).filter(Boolean).slice(0, 3);
});

const hasObjectContent = (value) => value && typeof value === 'object' && Object.keys(value).length > 0;
const traceReady = computed(() => {
    const trace = resolvedTrace.value ?? {};

    return hasObjectContent(trace)
        && (
            hasObjectContent(trace.audio)
            || hasObjectContent(trace.features)
            || hasObjectContent(trace.embeddings)
            || hasObjectContent(trace.logits)
            || hasObjectContent(trace.decoding)
            || hasObjectContent(trace.gop)
            || hasObjectContent(trace.expected_centric)
            || hasValue(trace.final_transcript)
        );
});
const traceSignature = computed(() => {
    const trace = resolvedTrace.value ?? {};

    return JSON.stringify({
        final: trace.final_transcript ?? '',
        audioSamples: trace.audio?.num_samples ?? '',
        featureShape: trace.features?.shape ?? [],
        embeddingShape: trace.embeddings?.shape ?? [],
        logitsShape: trace.logits?.shape ?? [],
        decoding: trace.decoding?.raw_transcript ?? '',
        heard: trace.expected_centric?.heard ?? '',
    });
});

const finalTranscript = computed(() => String(firstPresent(
    props.transcript,
    resolvedTrace.value?.final_transcript,
    props.asrResult?.displayed_transcript,
    props.asrResult?.corrected_transcript,
    props.asrResult?.transcript,
    props.asrResult?.raw_transcript,
) ?? '').trim());

const expectedText = computed(() => String(firstPresent(
    props.expectedText,
    resolvedTrace.value?.expected_centric?.expected,
    props.asrResult?.expected_text,
    props.asrResult?.expected,
) ?? '').trim());

const placeholderText = computed(() => props.isProcessing ? props.processingText : props.placeholder);

const isLockedByDefault = (character) => character === '\n' || character === ' ' || character === '\t';
const scrambleCharacter = () => scrambleCharacters[Math.floor(Math.random() * scrambleCharacters.length)];
const revealIndexes = (target) => target
    .split('')
    .map((character, index) => ({ character, index }))
    .filter(({ character }) => !isLockedByDefault(character))
    .map(({ index }) => index);

const batchSizeForStage = (stage, indexes) => {
    const count = indexes.length;

    if (count <= 0) return 1;

    if (stage.title === 'Final Transcript') {
        if (count <= 16) return 1;

        return Math.max(2, Math.ceil(count / 12));
    }

    if (count <= 12) return 1;

    return Math.max(2, Math.ceil(count / 4));
};

const renderScrambledTarget = (target, lockedIndexes, activeIndexes) => {
    const locked = new Set(lockedIndexes);
    const active = new Set(activeIndexes);

    return target
        .split('')
        .map((character, index) => {
            if (isLockedByDefault(character) || locked.has(index)) {
                return character;
            }

            if (active.has(index)) {
                return scrambleCharacter();
            }

            return ' ';
        })
        .join('');
};

const revealStage = async (stage, index, currentRunId) => {
    stageTitle.value = stage.title;
    stageNumber.value = index + 1;
    progress.value = Math.round((index / totalStages) * 100);

    const target = stage.body || 'Trace unavailable';

    if (reduceMotion) {
        displayText.value = target;
        progress.value = Math.round(((index + 1) / totalStages) * 100);
        return;
    }

    const indexes = revealIndexes(target);
    const batchSize = batchSizeForStage(stage, indexes);
    const lockedIndexes = [];

    if (!indexes.length) {
        displayText.value = target;
        progress.value = Math.round(((index + 1) / totalStages) * 100);
        return;
    }

    for (let start = 0; start < indexes.length; start += batchSize) {
        const activeIndexes = indexes.slice(start, start + batchSize);

        for (let tick = 0; tick < SCRAMBLES_PER_CHARACTER; tick += 1) {
            if (currentRunId !== runId) return;

            displayText.value = renderScrambledTarget(target, lockedIndexes, activeIndexes);
            await sleep(TICK_INTERVAL_MS);
        }

        if (currentRunId !== runId) return;

        lockedIndexes.push(...activeIndexes);
        displayText.value = renderScrambledTarget(target, lockedIndexes, []);
    }

    if (currentRunId !== runId) return;
    displayText.value = target;
    progress.value = Math.round(((index + 1) / totalStages) * 100);
};

const formatList = (items, formatter, emptyText) => {
    const normalized = asArray(items).filter(Boolean);

    if (!normalized.length) return emptyText;

    return normalized.map(formatter).join('\n');
};

function hasValue(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

const shapeText = (shape) => asArray(shape).length ? `[${shape.join(', ')}]` : '';
const valuesText = (values) => asArray(values).length ? values.map(formatValue).join(', ') : '';

const buildStages = () => {
    const trace = resolvedTrace.value ?? {};
    const result = props.asrResult ?? {};
    const audio = trace.audio ?? result.audio ?? result.ai_response?.audio ?? {};
    const features = trace.features ?? result.features ?? {};
    const embeddings = trace.embeddings ?? result.embeddings ?? {};
    const logits = trace.logits ?? result.logits ?? result.ai_response?.logits;
    const decoding = trace.decoding ?? result.decoding ?? {};
    const beams = trace.beam_search ?? result.beam_search ?? result.beam_candidates ?? result.ai_response?.beam_search;
    const gop = trace.gop ?? result.gop ?? {};
    const expectedCentric = trace.expected_centric ?? result.expected_centric ?? {};
    const gopScore = firstPresent(gop.score, result.gop_score, result.overall_gop_score, result.ai_response?.gop_score);
    const gopVerdict = firstPresent(gop.verdict, result.gop_decision, result.ai_response?.gop_decision);
    const match = expectedCentric.match ?? result.scoring?.accepted ?? result.accepted;
    const confidence = firstPresent(
        expectedCentric.confidence,
        result.stt_confidence,
        result.confidence,
        result.dynamic_correction_confidence,
        result.asr_spelling_variant_confidence,
    );
    const decodingSteps = asArray(decoding.partial_steps).length ? asArray(decoding.partial_steps) : asArray(decoding.steps);
    const topTokens = Array.isArray(logits) ? logits : asArray(logits?.top_tokens);
    const audioLines = [
        hasValue(audio.sample_rate) ? `Sample rate: ${formatValue(audio.sample_rate)} Hz` : '',
        hasValue(audio.duration_ms) ? `Duration: ${formatValue(audio.duration_ms)} ms` : '',
        hasValue(audio.num_samples) ? `Samples: ${formatValue(audio.num_samples)}` : '',
        valuesText(audio.pcm_preview) ? `PCM preview: ${valuesText(audio.pcm_preview)}` : '',
        valuesText(audio.byte_preview_binary) ? `Byte preview: ${valuesText(audio.byte_preview_binary)}` : '',
        hasValue(audio.rms) || hasValue(audio.peak) ? `RMS/Peak: ${formatValue(audio.rms)} / ${formatValue(audio.peak)}` : '',
    ].filter(Boolean);
    const featureLines = [
        features.type ? `Features: ${features.type}` : '',
        shapeText(features.shape) ? `Feature shape: ${shapeText(features.shape)}` : '',
        valuesText(features.preview) ? `Feature preview: ${valuesText(features.preview)}` : '',
        embeddings.source ? `Embedding source: ${embeddings.source}` : '',
        shapeText(embeddings.shape) ? `Embedding shape: ${shapeText(embeddings.shape)}` : '',
        valuesText(embeddings.pooled_preview ?? embeddings.preview) ? `Pooled preview: ${valuesText(embeddings.pooled_preview ?? embeddings.preview)}` : '',
        valuesText(embeddings.frame_preview) ? `Frame preview: ${valuesText(embeddings.frame_preview)}` : '',
    ].filter(Boolean);
    const gopPhonemes = formatList(
        gop.phoneme_scores,
        (item) => `${item.phoneme ?? item.phone ?? 'phoneme'}: ${formatValue(item.score ?? item.confidence)}`,
        '',
    );

    return [
        {
            title: 'Binary Stream',
            body: audioLines.length
                ? audioLines.join('\n')
                : (trace.binary ? String(trace.binary) : 'Audio trace unavailable.'),
        },
        {
            title: 'Embedding Space',
            body: featureLines.length ? featureLines.join('\n') : 'Feature and embedding trace unavailable.',
        },
        {
            title: 'Logits',
            body: formatList(
                topTokens,
                (item) => `${item.token ?? item.label ?? 'token'}: ${formatValue(item.score ?? item.value ?? item.logit)}${item.probability !== undefined ? ` / p=${formatValue(item.probability)}` : ''}`,
                'Logit trace unavailable.',
            ),
        },
        {
            title: 'Decoding',
            body: decodingSteps.length
                ? decodingSteps.join(' -> ')
                : [
                    asArray(decoding.tokens).length ? `Tokens: ${decoding.tokens.join(', ')}` : '',
                    asArray(decoding.token_ids).length ? `Token IDs: ${decoding.token_ids.join(', ')}` : '',
                    decoding.raw_transcript ?? decoding.raw ? `Raw: ${decoding.raw_transcript ?? decoding.raw}` : '',
                ].filter(Boolean).join('\n') || 'Decoding trace unavailable.',
        },
        {
            title: 'Beam Search',
            body: formatList(
                beams,
                (item, index) => `${index + 1}. ${item.candidate ?? item.text ?? item.transcript ?? 'candidate unavailable'}${item.score !== undefined ? ` (${formatValue(item.score)})` : ''}`,
                'Beam candidates unavailable.',
            ),
        },
        {
            title: 'GOP Score',
            body: [
                gopScore !== undefined ? `Pronunciation score: ${formatValue(gopScore)}` : 'GOP score unavailable.',
                gopVerdict !== undefined ? `Verdict: ${formatValue(gopVerdict)}` : '',
                gopPhonemes ? `Phonemes:\n${gopPhonemes}` : '',
            ].filter(Boolean).join('\n'),
        },
        {
            title: 'Expected-Centric Match',
            body: [
                expectedText.value ? `Expected: ${expectedText.value}` : 'Expected text unavailable.',
                finalTranscript.value ? `Heard: ${finalTranscript.value}` : 'Heard transcript pending.',
                match !== undefined ? `Match: ${formatValue(match)}` : 'Match verdict unavailable.',
                confidence !== undefined ? `Confidence: ${formatValue(confidence)}` : '',
            ].filter(Boolean).join('\n'),
        },
        {
            title: 'Final Transcript',
            body: finalTranscript.value || 'Final transcript unavailable.',
        },
    ];
};

const showError = () => {
    runId += 1;
    clearTimers();
    visualizing.value = true;
    stageTitle.value = 'ASR Error';
    stageNumber.value = 0;
    progress.value = 100;
    displayText.value = props.error || 'The ASR request failed.';
};

const showTraceWaiting = () => {
    visualizing.value = true;
    stageTitle.value = 'ASR Trace';
    stageNumber.value = 0;
    progress.value = 12;
    displayText.value = 'Audio uploaded.\nWaiting for real ASR trace data...';
};

const playSequence = async () => {
    if (!effectiveEnabled.value) return;

    runId += 1;
    const currentRunId = runId;
    clearTimers();
    visualizing.value = true;
    progress.value = 0;

    if (!traceReady.value && props.isProcessing) {
        let waitCycles = 0;

        while (currentRunId === runId && !props.error && props.isProcessing && !traceReady.value && waitCycles < 80) {
            showTraceWaiting();
            waitCycles += 1;
            await sleep(120);
        }
    }

    if (currentRunId !== runId || props.error) return;

    const stageCount = buildStages().length;

    for (let index = 0; index < stageCount - 1; index += 1) {
        if (currentRunId !== runId || props.error) return;

        await revealStage(buildStages()[index], index, currentRunId);
        if (currentRunId !== runId || props.error) return;
        await sleep(95);
    }

    let waitCycles = 0;
    while (currentRunId === runId && !props.error && !finalTranscript.value && waitCycles < 40) {
        stageTitle.value = 'Final Transcript';
        stageNumber.value = totalStages;
        progress.value = 88;
        displayText.value = 'Waiting for ASR result...';
        waitCycles += 1;
        await sleep(120);
    }

    if (currentRunId !== runId || props.error) return;

    await revealStage(buildStages()[stageCount - 1], stageCount - 1, currentRunId);
    await sleep(220);

    if (currentRunId === runId) {
        visualizing.value = false;
    }
};

watch(
    () => [props.isProcessing, props.replayKey],
    ([processing]) => {
        if (!effectiveEnabled.value) return;

        if (processing) {
            playSequence();
        }
    },
);

watch(
    () => props.error,
    (message) => {
        if (message && effectiveEnabled.value) {
            showError();
        }
    },
);

watch(
    () => [finalTranscript.value, props.replayKey, props.playOnResult, effectiveEnabled.value, props.isProcessing, traceReady.value, traceSignature.value],
    ([transcript, replayKey, playOnResult, enabled, processing, ready, signature]) => {
        if (!enabled || processing || props.error || !transcript) return;
        if (!ready && !playOnResult) return;

        const key = String(replayKey || signature || transcript);
        if (animatedResultKey.value === key) return;

        animatedResultKey.value = key;
        playSequence();
    },
    { immediate: true },
);

watch(effectiveEnabled, (value) => {
    if (!value) {
        runId += 1;
        clearTimers();
        visualizing.value = false;
    } else if (props.isProcessing) {
        playSequence();
    } else if (props.error) {
        showError();
    }
});

onBeforeUnmount(() => {
    runId += 1;
    clearTimers();
});
</script>

<template>
    <div
        v-if="effectiveEnabled && visualizing"
        :class="[boxClass, 'asr-visualizer-box overflow-y-auto']"
        role="status"
        aria-live="polite"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-[11px] font-black uppercase tracking-[0.16em] text-primary">{{ stageTitle }}</p>
                <p class="mt-0.5 text-[11px] font-bold text-muted">
                    {{ stageNumber ? `Stage ${stageNumber} of ${totalStages}` : 'Stopped' }}
                </p>
            </div>
            <span class="shrink-0 rounded-full bg-primary-light px-2.5 py-1 text-[11px] font-black text-primary ring-1 ring-primary/15">
                Re
            </span>
        </div>

        <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-primary-light">
            <div class="h-full rounded-full bg-gradient-to-r from-warning to-primary transition-all duration-200" :style="{ width: `${progress}%` }" />
        </div>

        <pre class="asr-visualizer-text m-0 whitespace-pre-wrap break-words font-mono text-[13px] font-bold leading-relaxed text-text sm:text-sm">{{ displayText }}</pre>
        <p v-if="resolvedTraceNotes.length" class="mt-3 text-[11px] font-semibold leading-snug text-muted">
            {{ resolvedTraceNotes.join(' ') }}
        </p>
    </div>

    <slot v-else name="normal" :transcript="finalTranscript" :placeholder="placeholderText">
        <textarea
            v-if="normalMode === 'textarea' && finalTranscript"
            :value="finalTranscript"
            :class="boxClass"
            readonly
            :placeholder="placeholderText"
        />
        <div
            v-else-if="normalMode === 'textarea'"
            :class="[boxClass, 'rd-transcript-empty-state']"
            role="status"
            aria-live="polite"
        >
            <span class="rd-transcript-empty-icon" aria-hidden="true">
                <MessageSquareText class="size-5" />
            </span>
            <span class="rd-transcript-empty-copy">
                <span class="rd-transcript-empty-title">{{ placeholderText }}</span>
                <span class="rd-transcript-empty-helper">Your spoken answer will be transcribed here.</span>
            </span>
        </div>
        <div v-else :class="boxClass">
            <span v-if="finalTranscript">{{ finalTranscript }}</span>
            <span v-else :class="placeholderClass">{{ placeholderText }}</span>
        </div>
    </slot>
</template>

<style scoped>
.asr-visualizer-box {
    border: 0;
    border-radius: 24px;
    background: transparent;
    min-height: 0;
    overscroll-behavior: contain;
}

.asr-visualizer-text {
    min-height: 4.5rem;
}

.rd-transcript-empty-state {
    display: flex;
    min-height: 0;
    height: 100%;
    align-items: center;
    gap: clamp(0.85rem, 1.5vw, 1.25rem);
    border: 0 !important;
    border-radius: 24px !important;
    background: transparent !important;
    box-shadow: none !important;
    color: var(--rd-text-main) !important;
    resize: none;
}

.rd-transcript-empty-icon {
    display: grid;
    width: clamp(2.6rem, 5.5dvh, 3.35rem);
    height: clamp(2.6rem, 5.5dvh, 3.35rem);
    flex: 0 0 auto;
    place-items: center;
    border: 2px solid var(--rd-story-border-soft);
    border-radius: 999px;
    background: var(--rd-story-surface);
    color: var(--rd-brown);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.18), 0 8px 14px rgba(54, 83, 101, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.rd-transcript-empty-copy {
    display: grid;
    min-width: 0;
    gap: 0.25rem;
}

.rd-transcript-empty-title {
    color: var(--rd-text-main);
    font-size: clamp(1rem, 2.3dvh, 1.35rem);
    font-weight: 900;
    line-height: 1.15;
}

.rd-transcript-empty-helper {
    color: rgba(111, 101, 52, 0.68);
    font-size: clamp(0.82rem, 1.8dvh, 1rem);
    font-weight: 800;
    line-height: 1.25;
}
</style>
