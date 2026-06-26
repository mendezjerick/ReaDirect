<script setup>
import { computed, ref } from 'vue';
import { BookOpen, ChevronRight, Flag } from 'lucide-vue-next';
import AgentSpeakerPanel from './AgentSpeakerPanel.vue';

const props = defineProps({
    agentType: { type: String, default: 'assessment' },
    agentState: { type: String, default: 'listening' },
    agentMessage: { type: String, required: true },
    progress: { type: Number, default: 0 },
    primaryLabel: { type: String, default: 'Submit' },
    primaryDisabled: { type: Boolean, default: false },
    promptImage: { type: String, default: '' },
});

const emit = defineEmits(['primary', 'agent-speaking-change']);

const showPromptImage = ref(false);
const hasPromptImage = computed(() => String(props.promptImage ?? '').trim().length > 0);
const progressWidth = computed(() => `${Math.max(0, Math.min(100, Number(props.progress) || 0))}%`);

const togglePromptImage = () => {
    if (!hasPromptImage.value) {
        return;
    }

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

                <img
                    v-if="showPromptImage && hasPromptImage"
                    :src="promptImage"
                    alt=""
                    class="h-full max-h-full w-full object-contain"
                >
                <slot v-else name="prompt" />
            </div>

            <div class="assessment-record-panel">
                <slot name="recorder" />
            </div>
        </section>

        <section class="assessment-progress-row" aria-label="Assessment progress">
            <div class="assessment-progress-track">
                <span class="assessment-progress-marker assessment-progress-marker--start" aria-hidden="true">
                    <BookOpen class="size-4" stroke-width="2.7" />
                </span>
                <div class="assessment-progress-fill" :style="{ width: progressWidth }" />
                <span class="assessment-progress-marker assessment-progress-marker--end" aria-hidden="true">
                    <Flag class="size-4" stroke-width="2.7" />
                </span>
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
                <div class="assessment-transcript-content">
                    <slot name="transcript" />
                </div>
                <slot name="status" />
            </div>
            <div v-if="$slots.qa" class="assessment-qa-row">
                <slot name="qa" />
            </div>
        </section>
    </section>
</template>

<style scoped>
.assessment-task-workspace {
    --assessment-gap: clamp(0.35rem, 0.9dvh, 0.65rem);
    --assessment-agent-row: clamp(7.5rem, 17dvh, 11.25rem);
    --assessment-prompt-row: clamp(9.5rem, 28dvh, 18rem);
    --assessment-progress-row: clamp(1.85rem, 3.8dvh, 2.35rem);
    --assessment-transcript-min: clamp(8.5rem, 24dvh, 15rem);

    display: grid;
    height: 100%;
    min-height: 0;
    grid-template-rows:
        var(--assessment-agent-row)
        minmax(8.5rem, var(--assessment-prompt-row))
        var(--assessment-progress-row)
        minmax(var(--assessment-transcript-min), 1fr);
    gap: var(--assessment-gap);
    overflow: hidden;
}

.assessment-prompt-record-grid {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 1fr) minmax(14rem, clamp(16rem, 28vw, 26rem));
    gap: clamp(0.5rem, 1vw, 0.75rem);
}

.assessment-prompt-panel,
.assessment-record-panel,
.assessment-transcript-panel,
.assessment-qa-row {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--rd-soft-border);
    border-radius: 24px;
    background: linear-gradient(180deg, rgba(255, 253, 247, 0.98), rgba(250, 247, 239, 0.96));
    box-shadow: var(--rd-card-shadow-soft);
}

.assessment-prompt-panel {
    position: relative;
    display: grid;
    min-height: 0;
    container-type: size;
    place-items: center;
    padding: clamp(0.55rem, 1.7dvh, 1.35rem);
}

.assessment-record-panel {
    display: flex;
    min-height: 0;
    align-items: stretch;
    justify-content: center;
    container-type: size;
    overflow: visible;
}

.assessment-record-panel :deep(.assessment-hold-recorder),
.assessment-record-panel :deep(.automatic-listening-recorder) {
    min-width: 0;
    min-height: 0;
    inline-size: 100%;
    flex: 1 1 auto;
}

.assessment-prompt-panel :deep(*) {
    max-width: 100%;
}

.assessment-prompt-panel :deep(.letter-prompt) {
    overflow-wrap: anywhere;
    word-break: normal;
    font-size: clamp(4rem, min(70cqh, 18cqw), 14rem);
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

.assessment-progress-row {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 4fr) minmax(8rem, 1fr);
    gap: clamp(0.45rem, 0.9vw, 0.7rem);
    overflow: visible;
    border: 0;
    border-radius: 999px;
    background: transparent;
    box-shadow: none;
}

.assessment-progress-track {
    position: relative;
    display: flex;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid rgba(54, 83, 101, 0.12);
    border-radius: 999px;
    background:
        radial-gradient(circle at 28% 50%, rgba(54, 83, 101, 0.08) 0 0.16rem, transparent 0.18rem),
        radial-gradient(circle at 42% 50%, rgba(54, 83, 101, 0.08) 0 0.16rem, transparent 0.18rem),
        radial-gradient(circle at 56% 50%, rgba(54, 83, 101, 0.08) 0 0.16rem, transparent 0.18rem),
        radial-gradient(circle at 70% 50%, rgba(54, 83, 101, 0.08) 0 0.16rem, transparent 0.18rem),
        var(--rd-card-cream);
    box-shadow: inset 0 2px 7px rgba(54, 83, 101, 0.08), 0 6px 12px rgba(35, 55, 70, 0.08);
}

.assessment-progress-marker {
    position: absolute;
    z-index: 3;
    top: 50%;
    display: grid;
    width: clamp(1.7rem, 3.6dvh, 2.2rem);
    height: clamp(1.7rem, 3.6dvh, 2.2rem);
    place-items: center;
    border-radius: 999px;
    background: var(--rd-card-warm);
    color: var(--rd-action-button);
    transform: translateY(-50%);
    pointer-events: none;
    box-shadow: 0 3px 0 rgba(111, 101, 52, 0.18), 0 5px 12px rgba(35, 55, 70, 0.12);
}

.assessment-progress-marker--start {
    left: 0;
}

.assessment-progress-marker--end {
    right: 0.15rem;
    color: var(--rd-action-button);
}

.assessment-progress-marker--end svg {
    fill: rgba(238, 193, 112, 0.75);
}

.assessment-progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--rd-secondary-orange), var(--rd-primary-orange));
    box-shadow: 0 4px 10px rgba(245, 133, 73, 0.18);
    transition: width 240ms ease;
}

.assessment-primary-action {
    display: inline-flex;
    min-width: 0;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: 0;
    outline: 0;
    appearance: none;
    -webkit-appearance: none;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--rd-action-button-light) 0%, var(--rd-action-button) 100%);
    padding-inline: 0.75rem;
    font-size: clamp(0.95rem, 1.6vh, 1.1rem);
    font-weight: 900;
    letter-spacing: 0.04em;
    color: #ffffff;
    text-transform: uppercase;
    box-shadow: 0 7px 0 var(--rd-action-button-dark), 0 10px 16px var(--rd-action-button-shadow);
}

.assessment-primary-action:focus,
.assessment-primary-action:focus-visible {
    outline: 0;
}

.assessment-primary-action:hover {
    background: linear-gradient(180deg, #1A7890 0%, #115A6C 100%);
}

.assessment-primary-action:active:not(:disabled) {
    transform: translateY(5px);
    box-shadow: 0 2px 0 var(--rd-action-button-dark), 0 5px 10px rgba(8, 49, 61, 0.18);
}

.assessment-primary-action:disabled {
    cursor: not-allowed;
    background: linear-gradient(180deg, #71919A 0%, #557781 100%);
    opacity: 0.65;
    box-shadow: 0 6px 0 rgba(8, 49, 61, 0.32);
}

.assessment-primary-action-label {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.assessment-transcript-section {
    display: grid;
    min-height: 0;
    grid-template-rows: minmax(0, 1fr) auto;
    gap: clamp(0.35rem, 0.75dvh, 0.5rem);
    overflow: hidden;
}

.assessment-transcript-panel {
    display: flex;
    min-height: 0;
    flex-direction: column;
    gap: clamp(0.3rem, 0.7dvh, 0.45rem);
    padding: clamp(0.45rem, 1dvh, 0.75rem);
}

.assessment-transcript-content {
    display: flex;
    min-height: 0;
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.assessment-qa-row {
    max-height: clamp(2.55rem, 6dvh, 3.25rem);
    padding: clamp(0.3rem, 0.75dvh, 0.45rem);
}

.assessment-transcript-content :deep(textarea),
.assessment-transcript-content :deep(.asr-visualizer-box) {
    min-height: 0;
    height: 100%;
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
}

@media (max-height: 720px) {
    .assessment-task-workspace {
        --assessment-gap: clamp(0.3rem, 0.75dvh, 0.5rem);
        --assessment-agent-row: clamp(6.25rem, 15dvh, 8.5rem);
        --assessment-prompt-row: clamp(8.5rem, 25dvh, 13rem);
        --assessment-progress-row: clamp(1.65rem, 3.4dvh, 2rem);
        --assessment-transcript-min: clamp(8rem, 26dvh, 12rem);
    }
}

@media (min-height: 900px) {
    .assessment-task-workspace {
        --assessment-agent-row: clamp(8rem, 16dvh, 11rem);
        --assessment-prompt-row: clamp(13rem, 29dvh, 20rem);
        --assessment-transcript-min: clamp(12rem, 28dvh, 18rem);
    }
}

@media (max-width: 760px) {
    .assessment-prompt-record-grid {
        grid-template-columns: minmax(0, 1fr) minmax(11rem, 34vw);
    }
}
</style>
