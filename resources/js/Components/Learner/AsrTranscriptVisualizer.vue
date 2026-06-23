<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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

const randomizeAfterCursor = (target, cursor) => target
    .split('')
    .map((character, index) => {
        if (index <= cursor || character === '\n' || character === ' ' || character === '\t') {
            return character;
        }

        return scrambleCharacters[Math.floor(Math.random() * scrambleCharacters.length)];
    })
    .join('');

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

    const frameCount = Math.max(10, Math.min(22, Math.ceil(target.length / 6)));

    for (let frame = 0; frame <= frameCount; frame += 1) {
        if (currentRunId !== runId) return;

        const cursor = Math.floor((target.length - 1) * (frame / frameCount));
        displayText.value = randomizeAfterCursor(target, cursor);
        await sleep(26);
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

const buildStages = () => {
    const trace = resolvedTrace.value ?? {};
    const result = props.asrResult ?? {};
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
    const decodingSteps = asArray(decoding.steps);

    return [
        {
            title: 'Binary Stream',
            body: trace.binary
                ? String(trace.binary)
                : 'Audio stream captured.\nBinary trace unavailable.',
        },
        {
            title: 'Embedding Space',
            body: [
                embeddings.shape ? `Vector shape: [${embeddings.shape.join(', ')}]` : 'Embedding trace unavailable',
                asArray(embeddings.preview).length ? `Preview: ${embeddings.preview.map(formatValue).join(', ')}` : '',
            ].filter(Boolean).join('\n'),
        },
        {
            title: 'Logits',
            body: formatList(
                logits,
                (item) => `${item.token ?? item.label ?? 'token'}: ${formatValue(item.score ?? item.value ?? item.logit)}`,
                'Logit trace unavailable.',
            ),
        },
        {
            title: 'Decoding',
            body: decodingSteps.length
                ? decodingSteps.join(' -> ')
                : (decoding.raw ? String(decoding.raw) : 'Decoding trace unavailable.'),
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

const playSequence = async () => {
    if (!effectiveEnabled.value) return;

    runId += 1;
    const currentRunId = runId;
    clearTimers();
    visualizing.value = true;
    progress.value = 0;

    const stages = buildStages();

    for (let index = 0; index < stages.length - 1; index += 1) {
        if (currentRunId !== runId || props.error) return;

        await revealStage(stages[index], index, currentRunId);
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

    await revealStage(buildStages()[stages.length - 1], stages.length - 1, currentRunId);
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
    () => [finalTranscript.value, props.replayKey, props.playOnResult, effectiveEnabled.value],
    ([transcript, replayKey, playOnResult, enabled]) => {
        if (!enabled || !playOnResult || props.isProcessing || props.error || !transcript) return;

        const key = String(replayKey || transcript);
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
        :class="[boxClass, 'asr-visualizer-box overflow-hidden']"
        role="status"
        aria-live="polite"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-[11px] font-black uppercase tracking-[0.16em] text-primary">{{ stageTitle }}</p>
                <p class="mt-0.5 text-[11px] font-bold text-slate-400">
                    {{ stageNumber ? `Stage ${stageNumber} of ${totalStages}` : 'Stopped' }}
                </p>
            </div>
            <span class="shrink-0 rounded-full bg-primary/8 px-2.5 py-1 text-[11px] font-black text-primary ring-1 ring-primary/15">
                Re
            </span>
        </div>

        <div class="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-200/70">
            <div class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 transition-all duration-200" :style="{ width: `${progress}%` }" />
        </div>

        <pre class="asr-visualizer-text m-0 whitespace-pre-wrap break-words font-mono text-[13px] font-bold leading-relaxed text-slate-800 sm:text-sm">{{ displayText }}</pre>
    </div>

    <slot v-else name="normal" :transcript="finalTranscript" :placeholder="placeholderText">
        <textarea
            v-if="normalMode === 'textarea'"
            :value="finalTranscript"
            :class="boxClass"
            readonly
            :placeholder="placeholderText"
        />
        <div v-else :class="boxClass">
            <span v-if="finalTranscript">{{ finalTranscript }}</span>
            <span v-else :class="placeholderClass">{{ placeholderText }}</span>
        </div>
    </slot>
</template>

<style scoped>
.asr-visualizer-box {
    background:
        linear-gradient(135deg, rgba(239, 246, 255, 0.95), rgba(255, 255, 255, 0.98)),
        #ffffff;
}

.asr-visualizer-text {
    min-height: 4.5rem;
}
</style>
