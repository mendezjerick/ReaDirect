<script setup>
import { computed, ref } from 'vue';
import { BookOpen, ChevronRight, Flag } from 'lucide-vue-next';
import AgentSpeakerPanel from './AgentSpeakerPanel.vue';

const props = defineProps({
    agentType:    { type: String,  default: 'assessment' },
    agentState:   { type: String,  default: 'listening' },
    agentMessage: { type: String,  required: true },
    progress:     { type: Number,  default: 0 },
    totalSteps:   { type: Number,  default: 0 },
    currentStep:  { type: Number,  default: 0 },
    primaryLabel:    { type: String,  default: 'Submit' },
    primaryDisabled: { type: Boolean, default: false },
    promptImage:     { type: String,  default: '' },
});

const emit = defineEmits(['primary', 'agent-speaking-change']);

const showPromptImage = ref(false);
const hasPromptImage  = computed(() => String(props.promptImage ?? '').trim().length > 0);
const progressWidth   = computed(() => `${Math.max(0, Math.min(100, Number(props.progress) || 0))}%`);

// Segmented pill progress
const segments = computed(() => {
    const total = props.totalSteps > 0 ? props.totalSteps : 0;
    if (total === 0) return [];
    return Array.from({ length: total }, (_, i) => ({
        filled: i < props.currentStep,
        current: i === props.currentStep - 1,
    }));
});
const useSegments = computed(() => segments.value.length > 0);

const togglePromptImage = () => {
    if (!hasPromptImage.value) return;
    showPromptImage.value = !showPromptImage.value;
};
</script>

<template>
    <section class="assessment-task-workspace">
        <AgentSpeakerPanel
            compact
            :agent-type="agentType"
            :state="agentState"
            :message="agentMessage"
            presentation="assessment-horizontal"
            @speaking-start="emit('agent-speaking-change', true)"
            @speaking-end="emit('agent-speaking-change', false)"
        />

        <section class="assessment-prompt-record-grid">
            <div class="assessment-prompt-panel">
                <div class="assessment-prompt-face">
                    <button
                        v-if="hasPromptImage"
                        type="button"
                        class="assessment-prompt-toggle"
                        :aria-pressed="showPromptImage"
                        aria-label="Toggle prompt image"
                        @click="togglePromptImage"
                    >
                        <ChevronRight class="size-5 transition-transform" :class="showPromptImage ? 'rotate-180' : ''" />
                    </button>

<<<<<<< HEAD
                <img
                    v-if="showPromptImage && hasPromptImage"
                    :src="promptImage"
                    alt=""
                    class="h-full max-h-full w-full object-contain"
                >
                <slot v-else name="prompt" />

                <!-- Right-side arrow guide -->
                <div class="assessment-prompt-arrow" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
=======
                    <img
                        v-if="showPromptImage && hasPromptImage"
                        :src="promptImage"
                        alt=""
                        class="h-full max-h-full w-full object-contain"
                    >
                    <slot v-else name="prompt" />
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
                </div>
            </div>

            <div class="assessment-record-panel">
                <slot name="recorder" />
            </div>
        </section>

        <section class="assessment-progress-row" aria-label="Assessment progress">
            <!-- Segmented pill bar -->
            <div class="assessment-progress-track">
<<<<<<< HEAD
                <template v-if="useSegments">
                    <div
                        v-for="(seg, i) in segments"
                        :key="i"
                        class="assessment-progress-segment"
                        :class="{
                            'assessment-progress-segment--filled': seg.filled,
                            'assessment-progress-segment--current': seg.current,
                        }"
                    />
                </template>
                <!-- Fallback: plain fill bar when no segments -->
                <div v-else class="assessment-progress-fill" :style="{ width: progressWidth }" />
=======
                <div class="assessment-progress-face">
                    <span class="assessment-progress-marker assessment-progress-marker--start" aria-hidden="true">
                        <BookOpen class="size-4" stroke-width="2.7" />
                    </span>
                    <div class="assessment-progress-fill" :style="{ width: progressWidth }" />
                    <span class="assessment-progress-marker assessment-progress-marker--end" aria-hidden="true">
                        <Flag class="size-4" stroke-width="2.7" />
                    </span>
                </div>
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
            </div>
            <button
                type="button"
                class="assessment-primary-action"
                :disabled="primaryDisabled"
                @click="emit('primary')"
            >
                <span class="assessment-primary-action-label">{{ primaryLabel }}</span>
            </button>
        </section>

        <section class="assessment-transcript-section">
            <div class="assessment-transcript-panel">
                <div class="assessment-transcript-face">
                    <div class="assessment-transcript-content">
                        <slot name="transcript" />
                    </div>
                    <slot name="status" />
                </div>
            </div>
            <div v-if="$slots.qa" class="assessment-qa-row">
                <div class="assessment-qa-face learner-frame">
                    <slot name="qa" />
                </div>
            </div>
        </section>
    </section>
</template>

<style scoped>
.assessment-task-workspace {
<<<<<<< HEAD
    --assessment-gap: clamp(0.35rem, 0.9dvh, 0.65rem);
    --assessment-agent-row: clamp(10.5rem, 24dvh, 16rem);
    --assessment-prompt-row: clamp(9.5rem, 28dvh, 18rem);
    --assessment-progress-row: clamp(1.85rem, 3.8dvh, 2.35rem);
    --assessment-transcript-min: clamp(8.5rem, 24dvh, 15rem);
    --atw-agent-h: 100%;
=======
    --assessment-gap: clamp(0.55rem, 1.25dvh, 1rem);
    --assessment-agent-row: clamp(8rem, 17dvh, 11.4rem);
    --assessment-progress-row: clamp(2.75rem, 6dvh, 4.6rem);
    --assessment-transcript-row: clamp(7rem, 16dvh, 10rem);
    --assessment-qa-strip-height: 2.1rem;
    --assessment-qa-strip-gap: 0.65rem;
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0

    display: grid;
    box-sizing: border-box;
    height: 100%;
    min-height: 0;
    grid-template-rows:
        var(--assessment-agent-row)
        minmax(8.5rem, 1fr)
        var(--assessment-progress-row)
        var(--assessment-transcript-row);
    gap: var(--assessment-gap);
    overflow: visible;
    padding-bottom: calc(var(--assessment-qa-strip-height) + var(--assessment-qa-strip-gap));
}

.assessment-prompt-record-grid {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 1fr) minmax(15rem, clamp(18rem, 30vw, 28rem));
    gap: clamp(0.75rem, 1.35vw, 1.1rem);
}

.assessment-prompt-panel,
.assessment-transcript-panel {
    min-width: 0;
    min-height: 0;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: var(--rd-radius-frame);
    background: var(--rd-story-surface);
    color: var(--rd-text-main);
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
    padding: 10px 12px 14px;
}

.assessment-prompt-face,
.assessment-transcript-face {
    min-width: 0;
    min-height: 0;
    border: 1.5px solid var(--rd-face-border);
    border-radius: var(--rd-radius-face);
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.assessment-prompt-panel {
    position: relative;
    display: grid;
    min-height: 0;
}

.assessment-prompt-face {
    position: relative;
    display: grid;
    height: 100%;
    container-type: size;
    place-items: center;
    overflow: hidden;
    padding: clamp(0.55rem, 1.7dvh, 1.35rem);
}

.assessment-record-panel {
    display: flex;
    min-height: 0;
    align-items: stretch;
    justify-content: center;
    container-type: size;
    overflow: visible;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.assessment-record-panel :deep(.assessment-hold-recorder),
.assessment-record-panel :deep(.automatic-listening-recorder) {
    min-width: 0;
    min-height: 0;
    inline-size: 100%;
    flex: 1 1 auto;
}

.assessment-prompt-face :deep(*) {
    max-width: 100%;
}

.assessment-prompt-face :deep(.letter-prompt) {
    overflow-wrap: anywhere;
    word-break: normal;
    font-size: clamp(6rem, min(70cqh, 18cqw), 13rem);
    color: var(--rd-text-main);
    text-shadow: 0 3px 0 rgba(255, 255, 255, 0.8), 0 6px 14px rgba(54, 83, 101, 0.18);
}

.assessment-prompt-toggle {
    position: absolute;
    right: 0.75rem;
    top: 0.75rem;
    z-index: 1;
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    border: 1px solid rgba(54, 83, 101, 0.14);
    border-radius: 9999px;
    background: var(--rd-card-cream);
    color: var(--rd-primary-orange);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.2), 0 8px 14px rgba(35, 55, 70, 0.08);
}

/* Right-side arrow guide */
.assessment-prompt-arrow {
    position: absolute;
    right: 0.55rem;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    width: clamp(1.6rem, 3.5dvh, 2.2rem);
    height: clamp(1.6rem, 3.5dvh, 2.2rem);
    border-radius: 9999px;
    background: #eff6ff;
    border: 1.5px solid #bfdbfe;
    color: #3b82f6;
    opacity: 0.75;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.12);
}

.assessment-progress-row {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 4fr) minmax(8rem, 1fr);
    gap: clamp(0.75rem, 1.2vw, 1.1rem);
    overflow: visible;
    border: 0;
    border-radius: 999px;
    background: transparent;
    box-shadow: none;
}

.assessment-progress-track {
    position: relative;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: 26px;
    background: var(--rd-story-surface);
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
    padding: 8px 14px 12px;
}

.assessment-progress-face {
    position: relative;
    display: flex;
<<<<<<< HEAD
    align-items: center;
    gap: clamp(0.18rem, 0.4vw, 0.3rem);
    padding: 0 clamp(0.4rem, 0.8vw, 0.65rem);
    overflow: hidden;
    background: #dbeafe;
=======
    height: 100%;
    min-height: 0;
    align-items: stretch;
    overflow: hidden;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 18px;
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.assessment-progress-marker {
    position: absolute;
    z-index: 3;
    top: 50%;
    display: grid;
    width: clamp(2.1rem, 4.5dvh, 3.1rem);
    height: clamp(2.1rem, 4.5dvh, 3.1rem);
    place-items: center;
    border: 2px solid rgba(238, 193, 112, 0.7);
    border-radius: 999px;
    background: #FFFDF7;
    color: var(--rd-brown);
    transform: translateY(-50%);
    pointer-events: none;
    box-shadow: 0 3px 0 rgba(111, 101, 52, 0.18), 0 6px 12px rgba(54, 83, 101, 0.12);
}

.assessment-progress-marker--start {
    left: 0.35rem;
    color: var(--rd-brown);
}

.assessment-progress-marker--end {
    right: 0.35rem;
    color: var(--rd-brown);
}

.assessment-progress-marker--end svg {
    fill: rgba(238, 193, 112, 0.75);
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
}

/* Segmented pill variant */
.assessment-progress-segment {
    flex: 1 1 0;
    height: clamp(0.55rem, 1.1dvh, 0.75rem);
    border-radius: 9999px;
    background: #bfdbfe;
    transition: background 300ms ease, transform 200ms ease;
}
.assessment-progress-segment--filled {
    background: #3b82f6;
}
.assessment-progress-segment--current {
    background: #2563eb;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.35);
    transform: scaleY(1.15);
}

/* Plain fill bar (fallback) */
.assessment-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #F58549 0%, #F2A65A 100%);
    box-shadow: inset 0 2px 0 rgba(255, 255, 255, 0.24), 0 4px 10px rgba(245, 133, 73, 0.18);
    transition: width 240ms ease;
}

.assessment-primary-action {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: 2px solid #D9652F;
    outline: 0;
    appearance: none;
    -webkit-appearance: none;
    border-radius: 999px;
    background: linear-gradient(180deg, #FF8A4C 0%, #F58549 100%);
    padding-inline: 0.75rem;
    font-size: clamp(0.95rem, 1.6vh, 1.1rem);
    font-weight: 900;
    letter-spacing: 0.04em;
    color: #ffffff;
    text-transform: uppercase;
    box-shadow: 0 7px 0 #B84B24, 0 12px 20px rgba(54, 83, 101, 0.25), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}

.assessment-primary-action:focus,
.assessment-primary-action:focus-visible {
    outline: 0;
}

.assessment-primary-action:hover {
    background: linear-gradient(180deg, #FF9A5C 0%, #F58549 100%);
}

.assessment-primary-action:active:not(:disabled) {
    transform: translateY(5px);
    box-shadow: 0 2px 0 #B84B24, 0 6px 12px rgba(54, 83, 101, 0.2);
}

.assessment-primary-action:disabled {
    cursor: not-allowed;
    border-color: rgba(111, 101, 52, 0.18);
    background: linear-gradient(180deg, #F7D3B0 0%, #F2A65A 100%);
    color: rgba(255, 255, 255, 0.9);
    opacity: 0.82;
    box-shadow: 0 5px 0 rgba(111, 101, 52, 0.2), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}

.assessment-primary-action-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-transcript-section {
    display: grid;
    width: 100%;
    min-height: 0;
    grid-template-rows: minmax(0, 1fr) auto;
    gap: clamp(0.35rem, 0.75dvh, 0.5rem);
    overflow: visible;
}

.assessment-transcript-panel {
    display: grid;
    min-height: 0;
}

.assessment-transcript-face {
    display: flex;
    height: 100%;
    flex-direction: column;
    gap: clamp(0.4rem, 0.8dvh, 0.65rem);
    overflow: hidden;
    padding: clamp(0.65rem, 1.2dvh, 1rem);
}

.assessment-transcript-content {
    display: flex;
    min-height: 0;
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.assessment-qa-row {
    position: fixed;
    left: 0;
    bottom: 0;
    z-index: 40;
    width: 100vw;
    min-width: 0;
    height: var(--assessment-qa-strip-height);
    overflow: hidden;
    border-block: 1px solid rgba(224, 207, 166, 0.55);
    border-inline: 0;
    border-radius: 0;
    background: rgba(255, 253, 248, 0.9);
    color: var(--rd-text-main);
    padding: 0.16rem 0;
    box-shadow: none;
}

.assessment-qa-face {
    display: flex;
    height: 100%;
    min-width: 0;
    align-items: center;
    overflow: hidden;
    padding-inline: 0.45rem;
}

.assessment-qa-face :deep(> *) {
    width: 100%;
    min-width: 0;
}

.assessment-qa-row :deep(label),
.assessment-qa-row :deep(.flex) {
    width: 100%;
    min-height: 0;
    color: rgba(35, 55, 70, 0.82);
    font-size: 0.7rem;
    line-height: 1;
}

.assessment-qa-row :deep(label) {
    flex: 1 1 auto;
    gap: 0.45rem;
}

.assessment-qa-row :deep(label > span) {
    flex: 0 0 auto;
    white-space: nowrap;
}

.assessment-qa-row :deep(input) {
    flex: 1 1 auto;
    width: 100%;
    min-height: 1.45rem !important;
    height: 1.45rem !important;
    border: 1px solid rgba(224, 207, 166, 0.72) !important;
    border-radius: 999px !important;
    background: rgba(255, 253, 248, 0.92) !important;
    color: var(--rd-text-main) !important;
    padding-block: 0 !important;
    font-size: 0.75rem !important;
    box-shadow: none !important;
}

.assessment-qa-row :deep(input:focus) {
    border-color: var(--rd-primary-orange) !important;
    box-shadow: 0 0 0 2px rgba(245, 133, 73, 0.14) !important;
}

.assessment-qa-row :deep(button) {
    min-height: 1.45rem !important;
    border: 1px solid rgba(224, 207, 166, 0.72) !important;
    border-radius: 999px !important;
    background: rgba(255, 253, 248, 0.88) !important;
    color: var(--rd-text-main) !important;
    padding: 0.12rem 0.7rem !important;
    font-size: 0.7rem !important;
    box-shadow: none !important;
}

.assessment-transcript-content :deep(textarea),
.assessment-transcript-content :deep(.asr-visualizer-box) {
    min-height: 0;
    height: 100%;
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
    border: 0 !important;
    border-radius: 24px !important;
    background: transparent !important;
    color: var(--rd-text-main) !important;
    box-shadow: none !important;
    outline: 0 !important;
}

.assessment-transcript-content :deep(textarea) {
    font-size: clamp(1.2rem, 2.2dvh, 1.7rem);
    line-height: 1.2;
    resize: none;
}

@media (max-height: 720px) {
    .assessment-task-workspace {
        --assessment-gap: clamp(0.3rem, 0.75dvh, 0.5rem);
        --assessment-agent-row: clamp(6.25rem, 15dvh, 8.5rem);
        --assessment-progress-row: clamp(2.45rem, 5.4dvh, 3.4rem);
        --assessment-transcript-row: clamp(6.5rem, 17dvh, 8rem);
        --assessment-qa-strip-gap: 0.45rem;
    }
}

@media (min-height: 900px) {
    .assessment-task-workspace {
        --assessment-agent-row: clamp(8rem, 16dvh, 11rem);
        --assessment-transcript-row: clamp(7.5rem, 15dvh, 10rem);
    }
}

@media (max-width: 760px) {
    .assessment-task-workspace {
        overflow-y: auto;
        grid-template-rows:
            auto
            auto
            auto
            minmax(7rem, auto);
    }

    .assessment-prompt-record-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .assessment-progress-row {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
