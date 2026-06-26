<script setup>
import { computed, ref } from 'vue';
import AgentSpeakerPanel from './AgentSpeakerPanel.vue';

const props = defineProps({
    agentType:       { type: String,  default: 'assessment' },
    agentState:      { type: String,  default: 'listening' },
    agentMessage:    { type: String,  required: true },
    progress:        { type: Number,  default: 0 },
    totalSteps:      { type: Number,  default: 0 },
    currentStep:     { type: Number,  default: 0 },
    primaryLabel:    { type: String,  default: 'Submit' },
    primaryDisabled: { type: Boolean, default: false },
    promptImage:     { type: String,  default: '' },
    variant:         { type: String,  default: '' },
    showArcs:        { type: Boolean, default: true },
    arcResult:       { type: String,  default: null }, // null | 'correct' | 'wrong'
    arcScore:        { type: Number,  default: null }, // 0-1 accuracy score
});

const emit = defineEmits(['primary', 'agent-speaking-change']);

const showPromptImage = ref(false);
const hasPromptImage  = computed(() => String(props.promptImage ?? '').trim().length > 0);

const togglePromptImage = () => {
    if (!hasPromptImage.value) return;
    showPromptImage.value = !showPromptImage.value;
};

// Arc fill percentages (0-100) for stroke-dasharray with pathLength="100"
// Fill is drawn from the END of the arc path using dashoffset trick
const normalizedScore = computed(() => {
    if (props.arcScore === null || props.arcScore === undefined) return null;
    // Normalize: if > 1 assume it's 0-100 scale
    const s = props.arcScore > 1 ? props.arcScore / 100 : props.arcScore;
    return Math.max(0, Math.min(1, s));
});

const yesFill = computed(() => {
    if (!props.arcResult) return 0;
    const s = normalizedScore.value;
    if (s === null) return props.arcResult === 'correct' ? 100 : 0;
    return Math.round(s * 100);
});

const noFill = computed(() => {
    if (!props.arcResult) return 0;
    const s = normalizedScore.value;
    if (s === null) return props.arcResult === 'wrong' ? 100 : 0;
    return Math.round((1 - s) * 100);
});

// For stroke-dasharray "fill gap" with pathLength=100, filling from the END:
// dasharray: "fill 100", dashoffset: "100 - fill"  → shows last `fill` % of path
const yesDash  = computed(() => `${yesFill.value} 100`);
 const yesOffset = computed(() => 100 - yesFill.value);
const noDash   = computed(() => `${noFill.value} 100`);
const noOffset  = computed(() => 100 - noFill.value);
</script>

<template>
    <section class="atw-workspace" :class="variant ? `atw-workspace--${variant}` : ''">

        <!-- ━━ ROW 1: Top cards — Prompt (left) | Transcript (right) ━━━━━━━━━━━━ -->
        <div class="atw-top-row">

            <!-- LEFT: Reading Prompt card -->
            <div class="atw-prompt-card">
                <p class="atw-card-title">Reading Prompt</p>

                <!-- Image toggle button -->
                <button
                    v-if="hasPromptImage"
                    type="button"
                    class="atw-image-toggle"
                    :aria-pressed="showPromptImage"
                    aria-label="Toggle prompt image"
                    @click="togglePromptImage"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <span>{{ showPromptImage ? 'Text' : 'Image' }}</span>
                </button>

                <div class="atw-prompt-inner">
                    <div class="atw-prompt-slot">
                        <Transition name="atw-slide" mode="out-in">
                            <img
                                v-if="showPromptImage && hasPromptImage"
                                :src="promptImage"
                                alt=""
                                class="atw-prompt-image"
                            >
                            <slot v-else name="prompt" />
                        </Transition>
                    </div>
                </div>

                <!-- tap hint -->
                <p
                    v-if="hasPromptImage"
                    class="atw-swipe-hint"
                    @click="togglePromptImage"
                >
                    {{ showPromptImage ? 'tap to see text →' : 'tap to see image →' }}
                </p>
            </div>

            <!-- RIGHT: Live Transcript card -->
            <div class="atw-right-col">
                <div class="atw-transcript-box">
                    <p class="atw-card-title">Live Transcript</p>
                    <div class="atw-t-content">
                        <slot name="transcript" />
                    </div>
                    <!-- Waveform decoration -->
                    <svg class="atw-waveform" width="72" height="24" viewBox="0 0 88 28" aria-hidden="true">
                        <rect x="2"  y="4"  width="4" height="20" rx="2" fill="currentColor"/>
                        <rect x="10" y="9"  width="4" height="10" rx="2" fill="currentColor"/>
                        <rect x="18" y="2"  width="4" height="24" rx="2" fill="currentColor"/>
                        <rect x="26" y="7"  width="4" height="14" rx="2" fill="currentColor"/>
                        <rect x="34" y="0"  width="4" height="28" rx="2" fill="currentColor"/>
                        <rect x="42" y="5"  width="4" height="18" rx="2" fill="currentColor"/>
                        <rect x="50" y="1"  width="4" height="26" rx="2" fill="currentColor"/>
                        <rect x="58" y="8"  width="4" height="12" rx="2" fill="currentColor"/>
                        <rect x="66" y="4"  width="4" height="20" rx="2" fill="currentColor"/>
                        <rect x="74" y="9"  width="4" height="10" rx="2" fill="currentColor"/>
                        <rect x="82" y="7"  width="4" height="14" rx="2" fill="currentColor"/>
                    </svg>
                    <slot name="status" />
                </div>

                <!-- QA Fallback below transcript -->
                <div v-if="$slots.qa" class="atw-qa-box">
                    <slot name="qa" />
                </div>
            </div>
        </div>

        <!-- ━━ ROW 2: Record controls row ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <div class="atw-mid-row">

            <!-- NO arc (left) -->
            <div class="atw-arc-side atw-arc-side--no" :class="{ 'atw-arc-side--inactive': arcResult === 'correct' && noFill === 0 }">
                <svg v-if="showArcs" class="atw-arc-svg atw-arc-svg--no" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                    <!-- Background track (always muted beige) -->
                    <path d="M 80 15 A 55 55 0 0 0 10 80" stroke="#e8e0d4" stroke-width="8" stroke-linecap="round"/>
                    <!-- Foreground fill (red, from the END of the path = left tip) -->
                    <path
                        v-if="noFill > 0"
                        d="M 80 15 A 55 55 0 0 0 10 80"
                        stroke="#ef4444"
                        stroke-width="8"
                        stroke-linecap="round"
                        pathLength="100"
                        :stroke-dasharray="noDash"
                        :stroke-dashoffset="noOffset"
                    />
                </svg>
                <span v-if="showArcs" class="atw-arc-icon atw-arc-icon--no"
                    :style="{ color: noFill > 0 ? '#ef4444' : '#c5bbb3' }"
                >✕</span>
                <span v-if="showArcs" class="atw-arc-label atw-arc-label--no"
                    :style="{ color: noFill > 0 ? '#ef4444' : '#c5bbb3' }"
                >{{ noFill > 0 ? `${noFill}%` : 'No' }}</span>
            </div>

            <!-- Center: record button -->
            <div class="atw-record-wrap">
                <div class="atw-record-panel">
                    <slot name="recorder" />
                </div>
            </div>

            <!-- YES arc (right) -->
            <div class="atw-arc-side atw-arc-side--yes" :class="{ 'atw-arc-side--inactive': arcResult === 'wrong' && yesFill === 0 }">
                <svg v-if="showArcs" class="atw-arc-svg atw-arc-svg--yes" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                    <!-- Background track (always muted beige) -->
                    <path d="M 40 15 A 55 55 0 0 1 110 80" stroke="#e8e0d4" stroke-width="8" stroke-linecap="round"/>
                    <!-- Foreground fill (green, from the END of the path = right tip) -->
                    <path
                        v-if="yesFill > 0"
                        d="M 40 15 A 55 55 0 0 1 110 80"
                        stroke="#22c55e"
                        stroke-width="8"
                        stroke-linecap="round"
                        pathLength="100"
                        :stroke-dasharray="yesDash"
                        :stroke-dashoffset="yesOffset"
                    />
                </svg>
                <span v-if="showArcs" class="atw-arc-icon atw-arc-icon--yes"
                    :style="{ color: yesFill > 0 ? '#22c55e' : '#c5bbb3' }"
                >✓</span>
                <span v-if="showArcs" class="atw-arc-label atw-arc-label--yes"
                    :style="{ color: yesFill > 0 ? '#22c55e' : '#c5bbb3' }"
                >Yes</span>
            </div>

            <!-- Next button (far right) -->
            <div class="atw-next-wrap">
                <button
                    type="button"
                    class="atw-next-btn"
                    :disabled="primaryDisabled"
                    @click="emit('primary')"
                >
                    {{ primaryLabel }}<span class="atw-next-arrow">→</span>
                </button>
            </div>
        </div>

        <!-- ━━ ROW 3: Agent dialogue (bottom full-width) ━━━━━━━━━━━━━━━━━━━━━━━━ -->
        <AgentSpeakerPanel
            class="atw-dialogue-panel"
            compact
            :agent-type="agentType"
            :state="agentState"
            :message="agentMessage"
            presentation="assessment-horizontal"
            @speaking-start="emit('agent-speaking-change', true)"
            @speaking-end="emit('agent-speaking-change', false)"
        />

    </section>
</template>

<style scoped>
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   ROOT WORKSPACE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.atw-workspace {
    display: grid;
    height: 100%;
    min-height: 0;
    grid-template-rows:
        clamp(11rem, 24dvh, 16rem)
        minmax(9rem, 1fr)
        clamp(9rem, 21dvh, 13rem);
    gap: clamp(0.4rem, 0.9dvh, 0.7rem);
    overflow: hidden;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   ROW 1 – TOP SPLIT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.atw-top-row {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: clamp(0.5rem, 1.1vw, 0.9rem);
}

/* Shared card title */
.atw-card-title {
    font-family: 'Fredoka', system-ui, sans-serif;
    font-size: clamp(0.75rem, 1.5dvh, 0.95rem);
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.02em;
    margin: 0 0 0.35rem;
    flex-shrink: 0;
}

/* ── LEFT: Prompt card ── */
.atw-prompt-card {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    background: #ffffff;
    border: 4px solid #3b82f6;
    border-radius: 20px;
    box-shadow: 0 0 0 2px #93c5fd, 0 6px 20px rgba(30, 58, 138, 0.08);
    overflow: hidden;
    padding: clamp(0.5rem, 1.2dvh, 0.8rem);
}

.atw-image-toggle {
    position: absolute;
    top: 0.55rem;
    right: 0.55rem;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.22rem 0.55rem 0.22rem 0.4rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(4px);
    border: 1.5px solid rgba(59, 130, 246, 0.45);
    border-radius: 9999px;
    color: #2563eb;
    font-family: 'Fredoka', system-ui, sans-serif;
    font-size: 0.68rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 150ms, transform 80ms;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
}
.atw-image-toggle:hover  { background: #fff; }
.atw-image-toggle:active { transform: scale(0.95); }

.atw-prompt-inner {
    flex: 1;
    display: grid;
    place-items: center;
    min-height: 0;
    container-type: size;
    padding: clamp(0.15rem, 0.4dvh, 0.35rem);
    padding-top: clamp(1.4rem, 2.8dvh, 1.8rem);
}
.atw-prompt-slot {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 0;
    width: 100%;
}
.atw-prompt-inner :deep(*) { max-width: 100%; }
.atw-prompt-inner :deep(.letter-prompt) {
    overflow-wrap: anywhere;
    font-size: clamp(2.5rem, min(55cqh, 15cqw), 12rem);
    color: #1e3a8a;
    font-weight: 700;
    font-family: 'Fredoka', system-ui, sans-serif;
    line-height: 1;
    text-align: center;
}
.atw-prompt-image {
    width: 100%;
    height: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 12px;
}
/* Revert prompt text colors to blue */
.atw-prompt-inner :deep(.assessment-prompt-text-body) {
    color: #1E3A8A !important;
    --prompt-font-size: clamp(1.3rem, min(5cqh, 5cqw), 2.8rem) !important;
}
.atw-prompt-inner :deep(.assessment-prompt-text-label) {
    color: #94a3b8 !important;
}
.atw-swipe-hint {
    text-align: center;
    padding: 0.15rem 0.5rem 0;
    font-family: 'Fredoka', system-ui, sans-serif;
    font-size: 0.65rem;
    color: rgba(37, 99, 235, 0.65);
    cursor: pointer;
    transition: color 0.2s;
    flex-shrink: 0;
}
.atw-swipe-hint:hover { color: #2563eb; }

/* Slide transition */
.atw-slide-enter-active,
.atw-slide-leave-active { transition: opacity 180ms ease, transform 180ms ease; }
.atw-slide-enter-from   { opacity: 0; transform: translateX(14px); }
.atw-slide-leave-to     { opacity: 0; transform: translateX(-14px); }

/* ── RIGHT: Transcript col ── */
.atw-right-col {
    display: flex;
    flex-direction: column;
    min-height: 0;
    gap: clamp(0.3rem, 0.6dvh, 0.5rem);
}

.atw-transcript-box {
    position: relative;
    display: flex;
    flex-direction: column;
    flex: 1 1 0;
    min-height: 0;
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid rgba(191, 219, 254, 0.6);
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.06);
    padding: clamp(0.4rem, 0.8dvh, 0.7rem) clamp(0.6rem, 1.2vw, 1rem);
    gap: 0.2rem;
    overflow: hidden;
}

.atw-t-content {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
    font-family: 'Fredoka', system-ui, sans-serif;
    font-size: clamp(1rem, 2dvh, 1.4rem);
    font-weight: 600;
    color: #1e3a8a;
}

.atw-t-content :deep(> textarea) {
    min-height: 0;
    height: 100%;
    width: 100%;
    flex: 1 1 auto;
    background: transparent !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    font-family: inherit;
    font-size: inherit;
    font-weight: inherit;
    color: inherit;
    padding: 0 !important;
    margin: 0 !important;
    resize: none;
    line-height: 1.2;
}
.atw-t-content :deep(> textarea)::placeholder {
    color: rgba(30, 58, 138, 0.38) !important;
    font-weight: 500;
}
.atw-t-content :deep(.asr-visualizer-box) {
    min-height: 0;
    width: 100%;
    flex: 1 1 auto;
    background: transparent !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    font-family: 'Fredoka', system-ui, sans-serif !important;
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: #1e3a8a !important;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.atw-waveform {
    position: absolute;
    right: clamp(0.6rem, 1.5vw, 1.1rem);
    top: 50%;
    transform: translateY(-50%);
    color: #bfdbfe;
    pointer-events: none;
}

/* QA box */
.atw-qa-box {
    flex-shrink: 0;
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid rgba(191, 219, 254, 0.5);
    box-shadow: 0 2px 8px rgba(30, 58, 138, 0.05);
    padding: clamp(0.35rem, 0.8dvh, 0.6rem) clamp(0.6rem, 1.2vw, 1rem);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   ROW 2 – CONTROLS (NO arc | recorder | YES arc | Next)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.atw-mid-row {
    display: grid;
    grid-template-columns: auto 1fr auto auto;
    align-items: center;
    justify-items: center;
    gap: clamp(0.4rem, 1vw, 0.8rem);
    min-height: 0;
    padding: clamp(0.3rem, 0.7dvh, 0.6rem) 0;
}

/* Arc sides — muted by default, colored when active */
.atw-arc-side {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    width: clamp(4rem, 9vw, 7rem);
    height: 100%;
    /* Default: muted/faded — no arcResult set yet */
    color: #d6cfc4; /* muted beige for svg currentColor */
    transition: color 300ms ease, opacity 300ms ease;
}

/* NO side: default muted, active = red */
.atw-arc-side--no {
    color: #d6cfc4;
}
.atw-arc-side--no.atw-arc-side--active {
    color: #ef4444;
}
.atw-arc-side--no.atw-arc-side--inactive {
    color: #d6cfc4;
    opacity: 0.6;
}

/* YES side: default muted, active = green */
.atw-arc-side--yes {
    color: #d6cfc4;
}
.atw-arc-side--yes.atw-arc-side--active {
    color: #22c55e;
}
.atw-arc-side--yes.atw-arc-side--inactive {
    color: #d6cfc4;
    opacity: 0.6;
}
.atw-arc-svg {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    height: auto;
    pointer-events: none;
    overflow: visible;
}

.atw-arc-icon {
    position: relative;
    z-index: 1;
    font-size: clamp(1rem, 2.5vw, 1.6rem);
    font-weight: 900;
    margin-bottom: 0.1rem;
    line-height: 1;
    color: inherit;
}
.atw-arc-icon--no  { }
.atw-arc-icon--yes { }

.atw-arc-label {
    position: relative;
    z-index: 1;
    font-family: 'Fredoka', system-ui, sans-serif;
    font-size: clamp(0.85rem, 1.8vw, 1.15rem);
    font-weight: 700;
    line-height: 1;
    color: inherit;
}
.atw-arc-label--no  { }
.atw-arc-label--yes { }

/* Record button center */
.atw-record-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 0;
    width: 100%;
}

.atw-record-panel {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
    max-width: calc(clamp(11rem, min(40vw, 40dvh), 24rem) - 2.5rem);
    max-height: calc(clamp(11rem, min(40vw, 40dvh), 24rem) - 2.5rem);
    aspect-ratio: 1 / 1;
    min-height: 0;
    container-type: size;
}
.atw-record-panel :deep(.assessment-hold-recorder),
.atw-record-panel :deep(.automatic-listening-recorder) {
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
}
.atw-record-panel :deep(.assessment-circle-button-frame) {
    --assessment-circle-button-size: clamp(6rem, min(65cqh, 65cqw), 11.5rem) !important;
    --assessment-circle-icon-size:   clamp(1.8rem, min(16cqh, 16cqw), 3.2rem) !important;
    --assessment-circle-text-size:   clamp(0.7rem, min(8cqh, 8cqw), 1.1rem) !important;
}

/* Next button */
.atw-next-wrap {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    padding-bottom: clamp(0.4rem, 0.9dvh, 0.75rem);
}
.atw-next-btn {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0 clamp(1rem, 2.5vw, 1.5rem);
    height: clamp(2.8rem, 7dvh, 3.8rem);
    border-radius: 14px;
    font-family: 'Fredoka', system-ui, sans-serif;
    font-size: clamp(0.9rem, 1.8vh, 1.1rem);
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #ffffff;
    background: #3b82f6;
    box-shadow: 0 4px 0 #1d4ed8;
    transition: transform 80ms, box-shadow 80ms, background 120ms;
    white-space: nowrap;
    min-width: clamp(5rem, 10vw, 7rem);
    justify-content: center;
}
.atw-next-btn:hover:not(:disabled)  { background: #2563eb; }
.atw-next-btn:active:not(:disabled) { transform: translateY(4px); box-shadow: 0 0 0 #1d4ed8; }
.atw-next-btn:disabled              { opacity: 0.4; cursor: not-allowed; }
.atw-next-arrow {
    font-size: 1.1em;
    line-height: 1;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   ROW 3 – AGENT DIALOGUE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.atw-dialogue-panel {
    width: 100%;
    height: 100% !important;
    margin: 0;
    --atw-agent-h: 100%;
}

/* Bigger dialogue text */
.atw-dialogue-panel :deep(.text-base),
.atw-dialogue-panel :deep(p),
.atw-dialogue-panel :deep(.agent-message) {
    font-size: clamp(1.1rem, 2.2vh, 1.4rem) !important;
}

/* Avatar card */
.atw-dialogue-panel :deep(.assessment-agent-strip) {
    height: 100%;
    padding: 0.6rem;
}
.atw-dialogue-panel :deep(.assessment-agent-card) {
    min-width: clamp(7rem, 18dvh, 12rem);
}
.atw-dialogue-panel :deep(.assessment-agent-square) {
    border-radius: 18px;
}
.atw-dialogue-panel :deep(.assessment-agent-name) {
    font-size: clamp(0.7rem, 1.4dvh, 0.9rem);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   SENTENCE VARIANT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.atw-workspace--sentence {
    grid-template-rows:
        clamp(11rem, 25dvh, 18rem)
        minmax(10rem, 1fr)
        clamp(10rem, 22dvh, 14rem);
}
.atw-workspace--sentence .atw-prompt-card .atw-prompt-inner :deep(.assessment-prompt-text) {
    align-self: stretch;
    justify-self: stretch;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    height: 100%;
    max-height: 100%;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   RESPONSIVE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
@media (max-height: 720px) {
    .atw-workspace {
        grid-template-rows:
            clamp(9rem, 18dvh, 12rem)
            minmax(9rem, 1fr)
            clamp(8rem, 18dvh, 11rem);
    }
    .atw-workspace--sentence {
        grid-template-rows:
            clamp(9rem, 20dvh, 14rem)
            minmax(9rem, 1fr)
            clamp(8rem, 18dvh, 11rem);
    }
}

@media (max-width: 640px) {
    .atw-workspace {
        border-radius: 0;
        padding: 0.5rem;
        gap: 0.5rem;
        grid-template-rows:
            auto
            auto
            auto;
        height: auto;
    }
    .atw-top-row {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    .atw-mid-row {
        grid-template-columns: auto 1fr auto auto;
        gap: 0.4rem;
    }
    .atw-arc-side {
        width: clamp(3rem, 7vw, 5rem);
    }
    .atw-arc-svg {
        width: clamp(3.5rem, 8vw, 6rem);
        height: clamp(3.5rem, 8vw, 6rem);
    }
    .atw-next-btn {
        min-width: 4rem;
        padding: 0 0.75rem;
    }
}
</style>
