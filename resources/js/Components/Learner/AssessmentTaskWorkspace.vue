<script setup>
import { computed } from 'vue';
import { BookOpen, Flag } from 'lucide-vue-next';
import AgentSpeakerPanel from './AgentSpeakerPanel.vue';

const props = defineProps({
    agentType: { type: String, default: 'assessment' },
    agentState: { type: String, default: 'listening' },
    agentMessage: { type: String, required: true },
    agentIntent: { type: String, default: '' },
    agentLineKey: { type: String, default: '' },
    ttsEnabled: { type: Boolean, default: true },
    progress: { type: Number, default: 0 },
    primaryLabel: { type: String, default: 'Submit' },
    primaryDisabled: { type: Boolean, default: false },
    promptImage: { type: String, default: '' },
    displayState: { type: String, default: 'item' },
    displayFlush: { type: Boolean, default: false },
});

const emit = defineEmits(['primary', 'agent-speaking-change']);

const progressWidth = computed(() => `${Math.max(0, Math.min(100, Number(props.progress) || 0))}%`);
const normalizedDisplayState = computed(() => (
    ['item', 'processing', 'result'].includes(props.displayState) ? props.displayState : 'item'
));
</script>

<template>
    <section
        class="assessment-task-workspace"
        :class="{
            'assessment-task-workspace--has-lower-section': $slots.transcript || $slots.status || $slots.qa,
            'assessment-task-workspace--has-qa': $slots.qa,
            'assessment-task-workspace--display-flush': displayFlush,
        }"
    >
        <AgentSpeakerPanel
            compact
            :agent-type="agentType"
            :state="agentState"
            :message="agentMessage"
            :intent="agentIntent"
            :line-key="agentLineKey"
            :tts-enabled="ttsEnabled"
            presentation="assessment-horizontal"
            @speaking-start="emit('agent-speaking-change', true)"
            @speaking-end="emit('agent-speaking-change', false)"
        />

        <section class="assessment-prompt-record-grid">
            <div class="assessment-prompt-panel">
                <div class="assessment-prompt-face">
                    <Transition name="assessment-display-fade" mode="out-in">
                        <div
                            v-if="normalizedDisplayState === 'processing'"
                            key="processing"
                            class="assessment-display-layer assessment-display-layer--processing"
                        >
                            <slot name="processing">
                                <slot name="transcript" />
                            </slot>
                        </div>
                        <div
                            v-else-if="normalizedDisplayState === 'result'"
                            key="result"
                            class="assessment-display-layer assessment-display-layer--result"
                        >
                            <slot name="result">
                                <slot name="transcript" />
                            </slot>
                        </div>
                        <div
                            v-else
                            key="item"
                            class="assessment-display-layer assessment-display-layer--item"
                        >
                            <slot name="prompt" />
                        </div>
                    </Transition>
                </div>
            </div>

            <div class="assessment-record-panel">
                <slot name="recorder" />
            </div>
        </section>

        <section class="assessment-progress-row" aria-label="Assessment progress">
            <div class="assessment-progress-track">
                <div class="assessment-progress-face">
                    <span class="assessment-progress-marker assessment-progress-marker--start" aria-hidden="true">
                        <BookOpen class="size-4" stroke-width="2.7" />
                    </span>
                    <div class="assessment-progress-fill" :style="{ width: progressWidth }" />
                    <span class="assessment-progress-marker assessment-progress-marker--end" aria-hidden="true">
                        <Flag class="size-4" stroke-width="2.7" />
                    </span>
                </div>
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

        <section v-if="$slots.transcript || $slots.status || $slots.qa" class="assessment-transcript-section">
            <div v-if="$slots.transcript" class="assessment-transcript-panel">
                <div class="assessment-transcript-face">
                    <div class="assessment-transcript-content">
                        <slot name="transcript" />
                    </div>
                </div>
            </div>
            <div v-if="$slots.status" class="assessment-status-row">
                <slot name="status" />
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
    --assessment-gap: 1rem;
    --assessment-agent-row: clamp(8rem, 17dvh, 11.4rem);
    --assessment-progress-row: clamp(2.75rem, 6dvh, 4.6rem);
    --assessment-transcript-row: clamp(7rem, 16dvh, 10rem);
    --assessment-qa-strip-height: 2.1rem;
    --assessment-qa-strip-gap: 0.65rem;

    display: grid;
    box-sizing: border-box;
    height: 100%;
    min-height: 0;
    grid-template-rows:
        var(--assessment-agent-row)
        minmax(8.5rem, 1fr)
        var(--assessment-progress-row);
    gap: var(--assessment-gap);
    overflow: visible;
    padding-bottom: 0;
}

.assessment-task-workspace--has-lower-section {
    grid-template-rows:
        var(--assessment-agent-row)
        minmax(8.5rem, 1fr)
        var(--assessment-progress-row)
        auto;
}

.assessment-task-workspace--has-qa {
    padding-bottom: calc(var(--assessment-qa-strip-height) + var(--assessment-qa-strip-gap));
}

.assessment-prompt-record-grid {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 1fr) minmax(15rem, clamp(18rem, 30vw, 28rem));
    column-gap: clamp(0.75rem, 1.35vw, 1.1rem);
    row-gap: var(--assessment-gap);
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

.assessment-task-workspace--display-flush .assessment-prompt-face {
    padding: 0;
}

.assessment-display-layer {
    display: grid;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
}

.assessment-display-layer--item,
.assessment-display-layer--result {
    place-items: center;
}

.assessment-display-layer--processing {
    align-content: start;
    justify-items: stretch;
    padding: clamp(0.45rem, 1.35dvh, 0.95rem);
}

.assessment-display-fade-enter-active,
.assessment-display-fade-leave-active {
    transition: opacity 180ms ease;
}

.assessment-display-fade-enter-from,
.assessment-display-fade-leave-to {
    opacity: 0;
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
    color: #000000;
    text-shadow: 0 3px 0 rgba(255, 255, 255, 0.8), 0 6px 14px rgba(54, 83, 101, 0.18);
}

.assessment-progress-row {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 4fr) minmax(8rem, 1fr);
    column-gap: clamp(0.75rem, 1.2vw, 1.1rem);
    row-gap: var(--assessment-gap);
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
}

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
    grid-template-rows: auto auto auto;
    gap: clamp(0.35rem, 0.75dvh, 0.5rem);
    overflow: visible;
}

.assessment-transcript-section:empty {
    display: none;
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

.assessment-status-row {
    display: grid;
    min-width: 0;
    gap: clamp(0.3rem, 0.65dvh, 0.5rem);
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

.assessment-display-layer--processing :deep(.asr-visualizer-box) {
    width: 100%;
    height: 100%;
    max-height: 100%;
    min-height: 0;
    overflow-y: auto;
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
}

.assessment-display-layer--processing :deep(.asr-visualizer-text) {
    min-height: 0;
    font-size: clamp(0.72rem, min(3.2cqh, 1.45cqw), 0.95rem);
    line-height: 1.32;
}

.assessment-display-layer--processing :deep(.asr-visualizer-box > div),
.assessment-display-layer--processing :deep(.asr-visualizer-box > p) {
    max-width: 100%;
}

@media (max-height: 720px) {
    .assessment-task-workspace {
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
            auto;
    }

    .assessment-task-workspace--has-lower-section {
        grid-template-rows:
            auto
            auto
            auto
            auto;
    }

    .assessment-prompt-record-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .assessment-progress-row {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 600px) and (orientation: portrait) {
    .assessment-task-workspace {
        --mobile-page-padding: 16px;
        --mobile-section-gap: 16px;
        --mobile-card-gap: 16px;
        --assessment-gap: var(--mobile-section-gap);
        --assessment-agent-row: clamp(118px, 32vw, 132px);
        --assessment-progress-row: auto;

        align-content: start;
        height: auto;
        min-height: 100%;
        grid-template-rows: auto auto auto;
        gap: var(--mobile-section-gap);
        overscroll-behavior: contain;
        padding-inline: 0;
        padding-bottom: 8px;
        overflow: visible;
    }

    .assessment-task-workspace--has-lower-section {
        grid-template-rows: auto auto auto auto;
    }

    .assessment-prompt-record-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: var(--mobile-card-gap);
    }

    .assessment-prompt-panel {
        width: 100%;
        min-height: clamp(130px, 34vw, 190px);
    }

    .assessment-prompt-face {
        min-height: inherit;
        padding: clamp(0.55rem, 2.8vw, 0.9rem);
    }

    .assessment-prompt-face :deep(.letter-prompt),
    .assessment-prompt-face :deep(.assessment-prompt-text--letter .assessment-prompt-text-body) {
        font-size: clamp(4rem, min(56cqh, 17cqw), 6.5rem);
    }

    .assessment-prompt-face :deep(.assessment-prompt-text--word .assessment-prompt-text-body) {
        font-size: clamp(1.7rem, min(30cqh, 9cqw), 3rem);
    }

    .assessment-prompt-face :deep(.assessment-prompt-text--sentence .assessment-prompt-text-body),
    .assessment-prompt-face :deep(.assessment-prompt-text--medium .assessment-prompt-text-body) {
        font-size: clamp(1.05rem, min(15cqh, 4.8cqw), 2rem);
    }

    .assessment-prompt-face :deep(.assessment-prompt-text--long .assessment-prompt-text-body) {
        font-size: clamp(0.85rem, min(10cqh, 3.5cqw), 1.35rem);
    }

    .assessment-record-panel {
        width: 100%;
        height: clamp(180px, 28vh, 240px);
        min-height: 0;
    }

    .assessment-record-panel :deep(.assessment-hold-recorder),
    .assessment-record-panel :deep(.automatic-listening-recorder) {
        width: 100%;
        height: 100%;
        min-height: 0;
    }

    .assessment-progress-row {
        width: 100%;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
        overflow: visible;
    }

    .assessment-progress-track {
        width: 100%;
        min-height: clamp(3.25rem, 12vw, 4rem);
        overflow: visible;
    }

    .assessment-progress-face,
    .assessment-primary-action,
    .assessment-primary-action-label {
        overflow: visible;
    }

    .assessment-primary-action {
        width: 100%;
        min-height: clamp(3.25rem, 13vw, 4rem);
        padding-inline: 1rem;
        font-size: clamp(0.95rem, 4vw, 1.05rem);
    }
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-task-workspace) {
    --mobile-page-padding: 16px;
    --mobile-section-gap: 16px;
    --mobile-card-gap: 16px;
    --assessment-gap: var(--mobile-section-gap);
    --assessment-agent-row: 124px;
    --assessment-progress-row: auto;

    align-content: start;
    height: auto;
    min-height: 100%;
    grid-template-rows: auto auto auto;
    gap: var(--mobile-section-gap);
    overscroll-behavior: contain;
    padding-inline: 0;
    padding-bottom: 8px;
    overflow: visible;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-task-workspace--has-lower-section) {
    grid-template-rows: auto auto auto auto;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-record-grid) {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--mobile-card-gap);
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-panel) {
    width: 100%;
    min-height: 133px;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face) {
    min-height: inherit;
    padding: clamp(0.55rem, 2.8vw, 0.9rem);
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face .letter-prompt),
:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face .assessment-prompt-text--letter .assessment-prompt-text-body) {
    font-size: clamp(4rem, min(56cqh, 17cqw), 6.5rem);
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face .assessment-prompt-text--word .assessment-prompt-text-body) {
    font-size: clamp(1.7rem, min(30cqh, 9cqw), 3rem);
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face .assessment-prompt-text--sentence .assessment-prompt-text-body),
:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face .assessment-prompt-text--medium .assessment-prompt-text-body) {
    font-size: clamp(1.05rem, min(15cqh, 4.8cqw), 2rem);
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-prompt-face .assessment-prompt-text--long .assessment-prompt-text-body) {
    font-size: clamp(0.85rem, min(10cqh, 3.5cqw), 1.35rem);
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-record-panel) {
    width: 100%;
    height: clamp(180px, 28vh, 240px);
    min-height: 0;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-record-panel .assessment-hold-recorder),
:global(body[data-qa-viewport='mobile-vertical'] .assessment-record-panel .automatic-listening-recorder) {
    width: 100%;
    height: 100%;
    min-height: 0;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-progress-row) {
    width: 100%;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
    overflow: visible;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-progress-track) {
    width: 100%;
    min-height: 3.25rem;
    overflow: visible;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-progress-face),
:global(body[data-qa-viewport='mobile-vertical'] .assessment-primary-action),
:global(body[data-qa-viewport='mobile-vertical'] .assessment-primary-action-label) {
    overflow: visible;
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-primary-action) {
    width: 100%;
    min-height: 3.25rem;
    padding-inline: 1rem;
    font-size: 1rem;
}
</style>
