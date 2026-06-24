<script setup>
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
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
                <div class="assessment-progress-fill" :style="{ width: progressWidth }" />
            </div>
            <button
                type="button"
                class="assessment-primary-action"
                :disabled="primaryDisabled"
                @click="emit('primary')"
            >
                {{ primaryLabel }}
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
    border: 1px solid rgb(226 232 240);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 20px rgb(15 23 42 / 0.05);
}

.assessment-prompt-panel {
    position: relative;
    display: grid;
    min-height: 0;
    place-items: center;
    padding: clamp(0.55rem, 1.7dvh, 1.35rem);
}

.assessment-record-panel {
    min-height: 0;
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
    border: 1px solid rgb(226 232 240);
    border-radius: 9999px;
    background: #ffffff;
    color: rgb(71 85 105);
    box-shadow: 0 6px 14px rgb(15 23 42 / 0.08);
}

.assessment-progress-row {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 4fr) minmax(8rem, 1fr);
    overflow: hidden;
    border: 1px solid rgb(226 232 240);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 8px 16px rgb(15 23 42 / 0.04);
}

.assessment-progress-track {
    display: flex;
    align-items: stretch;
    overflow: hidden;
    background: rgb(226 232 240);
}

.assessment-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, rgb(59 130 246), rgb(14 165 233));
    transition: width 240ms ease;
}

.assessment-primary-action {
    display: grid;
    min-width: 0;
    place-items: center;
    border-left: 1px solid rgb(226 232 240);
    background: rgb(59 130 246);
    padding-inline: 0.75rem;
    font-size: clamp(0.95rem, 1.6vh, 1.1rem);
    font-weight: 900;
    color: #ffffff;
}

.assessment-primary-action:hover {
    background: rgb(37 99 235);
}

.assessment-primary-action:disabled {
    cursor: not-allowed;
    opacity: 0.55;
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
