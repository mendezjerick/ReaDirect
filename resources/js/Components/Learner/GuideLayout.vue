<script setup>
import { router } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import BottomActionBar from '../BottomActionBar.vue';
import PrimaryButton from '../PrimaryButton.vue';
import AgentSpeakerPanel from './AgentSpeakerPanel.vue';

defineProps({
    progress: { type: Number, default: 0 },
    diagnosticStep: { type: String, default: '' },
    backUrl: { type: String, default: '' },
    backLabel: { type: String, default: 'Back' },
    hasBottomBar: { type: Boolean, default: true },
    layout: { type: String, default: 'split' },
    align: { type: String, default: 'center' },
    maxWidth: { type: String, default: '68rem' },
    agentType: { type: String, default: 'assessment' },
    agentMessage: { type: String, required: true },
    agentLineKey: { type: String, default: '' },
    agentState: { type: String, default: 'speaking' },
    agentAllowCongrats: { type: Boolean, default: false },
    eyebrow: { type: String, default: '' },
    dividerLabel: { type: String, default: '' },
    showAudioButton: { type: Boolean, default: true },
    framedMedia: { type: Boolean, default: true },
    primaryLabel: { type: String, default: '' },
    primaryDisabled: { type: Boolean, default: false },
    primaryHref: { type: String, default: '' },
});

const emit = defineEmits(['primary', 'agent-interaction-ended']);

const handlePrimary = (href) => {
    if (href) {
        router.visit(href);
        return;
    }

    emit('primary');
};
</script>

<template>
    <LearnerLayout
        :progress="progress"
        :diagnostic-step="diagnosticStep"
        :back-url="backUrl"
        :back-label="backLabel"
        :has-bottom-bar="hasBottomBar"
    >
        <div
            class="guide-layout"
            :class="[`guide-layout--${layout}`, `guide-layout--${align}`]"
            :style="{ '--guide-max': maxWidth }"
        >
            <div class="guide-agent guide-anim" style="--guide-delay: 0ms">
                <AgentSpeakerPanel
                    :agent-type="agentType"
                    :state="agentState"
                    :message="agentMessage"
                    :line-key="agentLineKey"
                    :show-audio-button="showAudioButton"
                    presentation="routing"
                    :framed-media="framedMedia"
                    :allow-congrats="agentAllowCongrats"
                    @interaction-ended="emit('agent-interaction-ended', $event)"
                />
            </div>

            <div class="guide-content">
                <header v-if="eyebrow || $slots.title" class="guide-headline guide-anim" style="--guide-delay: 60ms">
                    <p v-if="eyebrow" class="guide-eyebrow">{{ eyebrow }}</p>
                    <h1 v-if="$slots.title" class="guide-title">
                        <slot name="title" />
                    </h1>
                </header>

                <div v-if="dividerLabel" class="guide-divider guide-anim" style="--guide-delay: 130ms" aria-hidden="true">
                    <span class="guide-divider-line" />
                    <span class="guide-divider-text">{{ dividerLabel }}</span>
                    <span class="guide-divider-line" />
                </div>

                <slot />
            </div>
        </div>

        <BottomActionBar v-if="$slots.actions || primaryLabel">
            <slot v-if="$slots.actions" name="actions" />
            <div v-else class="guide-action-bar">
                <div class="guide-action-side">
                    <slot name="secondary-action" />
                </div>
                <div class="guide-action-primary">
                    <PrimaryButton
                        orange-hover
                        :disabled="primaryDisabled"
                        class="guide-primary-button"
                        @click="handlePrimary(primaryHref)"
                    >
                        {{ primaryLabel }}
                        <slot name="primary-icon" />
                    </PrimaryButton>
                </div>
                <div class="guide-action-side guide-action-side--right">
                    <slot name="tertiary-action" />
                </div>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style>
.guide-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.75rem;
    max-width: var(--guide-max, 68rem);
    margin-inline: auto;
    padding-bottom: 2rem;
}

@media (min-width: 768px) {
    .guide-layout--split {
        grid-template-columns: 1fr 1.1fr;
        gap: 2.5rem;
    }

    .guide-layout--split.guide-layout--center {
        align-items: center;
    }

    .guide-layout--split.guide-layout--start {
        align-items: start;
    }
}

.guide-layout--stacked {
    justify-items: center;
    text-align: center;
}

.guide-layout--stacked .guide-agent {
    width: min(100%, 32rem);
}

.guide-layout--stacked .guide-content {
    width: 100%;
    justify-items: stretch;
}

.guide-layout--stacked .guide-headline {
    text-align: center;
}

.guide-agent,
.guide-content {
    min-width: 0;
}

.guide-content {
    display: grid;
    gap: 1.25rem;
}

.guide-eyebrow {
    margin-bottom: 0.4rem;
    color: var(--rd-primary-orange);
    font-size: 0.67rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    opacity: 0.65;
}

.guide-title {
    color: var(--rd-text-main);
    font-size: clamp(1.85rem, 3.8vw, 2.7rem);
    font-weight: 900;
    line-height: 1.07;
    letter-spacing: 0;
}

.guide-title-accent {
    color: var(--rd-primary-orange);
}

.guide-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.guide-divider-line {
    height: 1.5px;
    flex: 1;
    border-radius: 999px;
    background: var(--rd-frame-border);
}

.guide-divider-text {
    color: var(--rd-text-muted);
    font-size: 0.67rem;
    font-weight: 900;
    letter-spacing: 0.17em;
    text-transform: uppercase;
    white-space: nowrap;
}

.guide-traits {
    display: grid;
    gap: 0.7rem;
}

.guide-trait,
.guide-panel,
.guide-progress-card,
.guide-question-card {
    border: 2px solid var(--rd-story-border);
    border-radius: 22px;
    background: var(--rd-story-surface);
    box-shadow:
        0 5px 0 var(--rd-lip),
        0 7px 0 var(--rd-lip-dark),
        0 16px 22px -8px var(--rd-shadow);
}

.guide-trait {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.1rem 1.15rem;
}

.guide-trait-icon {
    display: grid;
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    place-items: center;
    border: 1.5px solid rgba(238, 193, 112, 0.38);
    border-radius: 0.75rem;
    background: linear-gradient(135deg, rgba(245, 133, 73, 0.12), rgba(238, 193, 112, 0.12));
    color: var(--rd-primary-orange);
}

.guide-trait-icon--teal {
    border-color: rgba(54, 83, 101, 0.22);
    background: linear-gradient(135deg, rgba(54, 83, 101, 0.12), rgba(42, 69, 87, 0.08));
    color: #365365;
}

.guide-trait-icon--violet {
    border-color: rgba(167, 139, 250, 0.25);
    background: linear-gradient(135deg, rgba(167, 139, 250, 0.14), rgba(139, 92, 246, 0.08));
    color: #7c3aed;
}

.guide-trait-icon--green {
    border-color: rgba(16, 185, 129, 0.24);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(52, 211, 153, 0.08));
    color: #059669;
}

.guide-trait-body {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.15rem;
}

.guide-trait-label {
    color: var(--rd-text-main);
    font-size: 0.9rem;
    font-weight: 900;
    line-height: 1.2;
}

.guide-trait-desc {
    color: var(--rd-text-muted);
    font-size: 0.78rem;
    font-weight: 700;
    line-height: 1.3;
}

.guide-panel {
    overflow: hidden;
    padding: 10px 12px 14px;
}

.guide-panel-face {
    border: 1.5px solid var(--rd-face-border);
    border-radius: 18px;
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.guide-dev-panel,
.guide-status {
    border-radius: 1rem;
    padding: 1rem 1.15rem;
}

.guide-dev-panel {
    border: 1px solid rgba(238, 193, 112, 0.6);
    background: rgba(255, 248, 223, 0.72);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

.guide-status--error {
    border: 1px solid rgba(248, 113, 113, 0.32);
    background: #fff1f2;
    color: #dc2626;
    font-size: 0.86rem;
    font-weight: 800;
}

.guide-status--warning {
    border: 1px solid rgba(251, 191, 36, 0.35);
    background: #fffbeb;
    color: #b45309;
    font-size: 0.86rem;
    font-weight: 800;
}

.guide-action-bar {
    display: grid;
    width: 100%;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
}

.guide-action-side {
    display: flex;
    min-width: 0;
    justify-content: flex-start;
}

.guide-action-side--right {
    justify-content: flex-end;
}

.guide-action-primary {
    display: flex;
    justify-content: center;
}

.guide-primary-button {
    gap: 0.75rem;
}

.guide-story-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.guide-story-option {
    display: block;
    width: 100%;
    padding: 0;
    border: 0;
    border-radius: var(--rd-radius-frame);
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition: transform 0.15s ease;
}

.guide-story-option:hover:not(.guide-story-option--selected) {
    transform: translateY(-2px);
}

.guide-story-card {
    position: relative;
    display: flex;
    min-height: 11rem;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.1rem 1rem 1.3rem;
    transition: background 0.2s ease;
}

.guide-story-option--selected .rd-card {
    border-color: var(--rd-primary-orange);
}

.guide-story-option--selected .guide-story-card {
    background: rgba(245, 133, 73, 0.04);
}

.guide-story-check {
    position: absolute;
    top: 0.7rem;
    right: 0.7rem;
    display: grid;
    width: 1.4rem;
    height: 1.4rem;
    place-items: center;
    border-radius: 50%;
    background: var(--rd-primary-orange);
    color: #fff;
    opacity: 0;
    transform: scale(0.6);
    transition: opacity 0.2s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.guide-story-check--on,
.guide-story-option:not(.guide-story-option--selected):hover .guide-story-check {
    opacity: 1;
    transform: scale(1);
}

.guide-story-option:not(.guide-story-option--selected):hover .guide-story-check {
    opacity: 0.22;
}

.guide-story-badge {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    place-items: center;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--rd-primary-orange) 0%, #e0732e 100%);
    color: #fff;
    font-size: 1.15rem;
    font-weight: 900;
    line-height: 1;
    box-shadow: 0 3px 0 rgba(190, 75, 15, 0.28), 0 6px 12px rgba(245, 133, 73, 0.2);
}

.guide-story-body {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 0.2rem;
}

.guide-story-label,
.guide-kicker {
    color: var(--rd-text-muted);
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.guide-story-title {
    color: var(--rd-text-main);
    font-size: 0.96rem;
    font-weight: 900;
    line-height: 1.25;
}

.guide-story-cue {
    margin-top: auto;
    color: var(--rd-text-muted);
    font-size: 0.68rem;
    font-weight: 800;
    opacity: 0.55;
    transition: color 0.2s ease, opacity 0.2s ease;
}

.guide-story-cue--active {
    color: var(--rd-primary-orange);
    opacity: 1;
}

.guide-progress-card {
    display: grid;
    gap: 0.7rem;
    padding: 0.85rem 1rem 1rem;
}

.guide-progress-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.guide-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.1);
    padding: 0.35rem 0.75rem;
    color: var(--rd-primary-orange);
    font-size: 0.74rem;
    font-weight: 900;
    line-height: 1;
    white-space: nowrap;
}

.guide-pill--muted {
    background: rgba(54, 83, 101, 0.08);
    color: var(--rd-text-muted);
}

.guide-progress-track {
    height: 0.75rem;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(54, 83, 101, 0.1);
    box-shadow: inset 0 1px 2px rgba(54, 83, 101, 0.14);
}

.guide-progress-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--rd-primary-orange), #f2a65a);
    box-shadow: 0 2px 8px rgba(245, 133, 73, 0.24);
    transition: width 0.35s ease;
}

.guide-question-card {
    overflow: hidden;
    padding: 1.15rem;
}

.guide-question-header {
    display: flex;
    align-items: flex-start;
    gap: 0.9rem;
    margin-bottom: 1rem;
}

.guide-question-icon {
    display: grid;
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    place-items: center;
    border-radius: 1rem;
    background: linear-gradient(135deg, var(--rd-primary-orange), #f2a65a);
    color: #fff;
    box-shadow: 0 5px 0 rgba(190, 75, 15, 0.24), 0 12px 18px rgba(245, 133, 73, 0.16);
}

.guide-question-text {
    min-width: 0;
    color: var(--rd-text-main);
    font-size: clamp(1.22rem, 2.2vw, 1.8rem);
    font-weight: 900;
    line-height: 1.13;
}

.guide-choice-grid {
    display: grid;
    gap: 0.65rem;
}

@media (min-width: 640px) {
    .guide-choice-grid--two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.guide-choice {
    display: grid;
    min-height: 3.45rem;
    grid-template-columns: 2rem minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.75rem;
    border: 2px solid rgba(54, 83, 101, 0.14);
    border-radius: 18px;
    background: #fff;
    padding: 0.7rem 0.9rem;
    color: var(--rd-text-main);
    font-size: 0.98rem;
    font-weight: 900;
    text-align: left;
    box-shadow: 0 10px 18px rgba(54, 83, 101, 0.08);
    transition: transform 0.16s ease, border-color 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
}

.guide-choice:hover {
    transform: translateY(-1px);
    border-color: rgba(245, 133, 73, 0.42);
}

.guide-choice--selected {
    border-color: var(--rd-primary-orange);
    background: rgba(245, 133, 73, 0.08);
    color: var(--rd-primary-orange);
    box-shadow: 0 12px 20px rgba(245, 133, 73, 0.12);
}

.guide-choice-dot {
    display: grid;
    width: 1.85rem;
    height: 1.85rem;
    place-items: center;
    border: 3px solid rgba(54, 83, 101, 0.16);
    border-radius: 50%;
    transition: border-color 0.16s ease;
}

.guide-choice--selected .guide-choice-dot {
    border-color: var(--rd-primary-orange);
}

.guide-choice-dot::after {
    display: block;
    width: 0.8rem;
    height: 0.8rem;
    border-radius: 50%;
    background: var(--rd-primary-orange);
    content: "";
    opacity: 0;
    transform: scale(0.5);
    transition: opacity 0.16s ease, transform 0.16s ease;
}

.guide-choice--selected .guide-choice-dot::after {
    opacity: 1;
    transform: scale(1);
}

.guide-word-pair {
    display: grid;
    gap: 1rem;
    padding: 1.1rem;
}

.guide-word-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 0.85rem;
}

.guide-word {
    min-width: 0;
    border-radius: 18px;
    background: #fff;
    padding: 1.2rem 1rem;
    color: var(--rd-text-main);
    font-size: clamp(1.8rem, 4vw, 3rem);
    font-weight: 900;
    line-height: 1;
    text-align: center;
    box-shadow: 0 8px 18px rgba(54, 83, 101, 0.1);
}

.guide-word-divider {
    color: rgba(54, 83, 101, 0.24);
    font-size: 2rem;
    font-weight: 900;
}

.guide-rhyme-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem;
}

.guide-rhyme-button {
    display: flex;
    min-height: 5.2rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    border: 2px solid rgba(54, 83, 101, 0.14);
    border-radius: 20px;
    background: #fff;
    color: var(--rd-text-muted);
    font-size: 1.05rem;
    font-weight: 900;
    transition: transform 0.16s ease, border-color 0.16s ease, color 0.16s ease, background 0.16s ease;
}

.guide-rhyme-button:hover {
    transform: translateY(-1px);
}

.guide-rhyme-button--yes:hover,
.guide-rhyme-button--yes.guide-rhyme-button--selected {
    border-color: #10b981;
    background: #ecfdf5;
    color: #047857;
}

.guide-rhyme-button--no:hover,
.guide-rhyme-button--no.guide-rhyme-button--selected {
    border-color: #f43f5e;
    background: #fff1f2;
    color: #be123c;
}

.guide-rhyme-icon {
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    border-radius: 50%;
    background: rgba(54, 83, 101, 0.08);
}

.guide-rhyme-button--yes .guide-rhyme-icon {
    background: #d1fae5;
    color: #059669;
}

.guide-rhyme-button--no .guide-rhyme-icon {
    background: #ffe4e6;
    color: #e11d48;
}

.guide-anim {
    animation: guideIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--guide-delay, 0ms);
}

@keyframes guideIn {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 560px) {
    .guide-story-grid,
    .guide-rhyme-options {
        grid-template-columns: 1fr;
    }

    .guide-word-row {
        grid-template-columns: 1fr;
    }

    .guide-word-divider {
        text-align: center;
    }

    .guide-progress-meta {
        align-items: flex-start;
        flex-direction: column;
    }

    .guide-action-bar {
        grid-template-columns: 1fr;
        justify-items: center;
    }

    .guide-action-side,
    .guide-action-side--right {
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    .guide-anim,
    .guide-choice,
    .guide-story-option,
    .guide-story-check,
    .guide-story-cue,
    .guide-rhyme-button {
        animation: none;
        transition: none;
    }
}
</style>
