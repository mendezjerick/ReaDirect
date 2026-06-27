<script setup>
import { useForm } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
import { Clock, Compass, Heart } from 'lucide-vue-next';

const props = defineProps({
    developerRetest: Object,
});

const form = useForm({});
const retestForm = useForm({});
const start = () => form.post('/learner/diagnostic/start');
const startDeveloperRetest = () => retestForm.post('/learner/diagnostic/developer-retest');
</script>

<template>
    <LearnerLayout :progress="25" diagnostic-step="intro">
        <!-- Full-width landscape split — no sidebar slot -->

        <div class="ds-landscape">

            <!-- LEFT: Miss Vivian, center stage -->
            <div class="ds-left ds-anim" style="--ds-delay: 0ms">
                <AgentSpeakerPanel
                    agent-type="assessment"
                    state="speaking"
                    message="We will do a short reading check together. I will guide each step — just try your best!"
                    show-audio-button
                    presentation="routing"
                />
            </div>

            <!-- RIGHT: Welcome content -->
            <div class="ds-right">

                <header class="ds-headline ds-anim" style="--ds-delay: 60ms">
                    <p class="ds-eyebrow">Reading Assessment</p>
                    <h1 class="ds-title">
                        Your <span class="ds-title-accent">Reading</span> Check
                    </h1>
                </header>

                <!-- Divider -->
                <div class="ds-divider ds-anim" style="--ds-delay: 130ms" aria-hidden="true">
                    <span class="ds-divider-line" />
                    <span class="ds-divider-text">What to expect</span>
                    <span class="ds-divider-line" />
                </div>

                <!-- Three trait cards — vertical on desktop, horizontal on mobile -->
                <div class="ds-traits">
                    <div class="ds-trait ds-anim" style="--ds-delay: 200ms">
                        <span class="ds-trait-icon"><Clock class="size-5" /></span>
                        <div class="ds-trait-body">
                            <span class="ds-trait-label">About 5 minutes</span>
                            <span class="ds-trait-desc">A short check — won't take long.</span>
                        </div>
                    </div>
                    <div class="ds-trait ds-anim" style="--ds-delay: 285ms">
                        <span class="ds-trait-icon"><Compass class="size-5" /></span>
                        <div class="ds-trait-body">
                            <span class="ds-trait-label">Guided step by step</span>
                            <span class="ds-trait-desc">Miss Vivian walks you through each part.</span>
                        </div>
                    </div>
                    <div class="ds-trait ds-anim" style="--ds-delay: 370ms">
                        <span class="ds-trait-icon"><Heart class="size-5" /></span>
                        <div class="ds-trait-body">
                            <span class="ds-trait-label">No pressure at all</span>
                            <span class="ds-trait-desc">Just try your best — that's all we need.</span>
                        </div>
                    </div>
                </div>

                <!-- Developer retest (hidden in normal flow) -->
                <div
                    v-if="props.developerRetest?.enabled"
                    class="ds-anim mt-4 rounded-2xl border border-amber-200/60 bg-amber-50 p-5 ring-1 ring-amber-200/40"
                    style="--ds-delay: 420ms"
                >
                    <p class="text-[14px] font-black text-amber-700">
                        Developer testing only. This starts a new sandbox diagnostic attempt for QA testing and preserves previous attempts.
                    </p>
                    <SecondaryButton class="mt-3" :disabled="retestForm.processing" @click="startDeveloperRetest">
                        Start New Developer Test Attempt
                    </SecondaryButton>
                </div>
            </div>
        </div>

        <BottomActionBar>
            <PrimaryButton :disabled="form.processing" @click="start">
                Begin reading check
            </PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* ─── Landscape grid ─────────────────────────────────── */
.ds-landscape {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.75rem;
    max-width: 68rem;
    margin-inline: auto;
    padding-bottom: 2rem;
}

@media (min-width: 768px) {
    .ds-landscape {
        grid-template-columns: 1fr 1.1fr;
        gap: 2.5rem;
        align-items: center;
    }
}

/* ─── Left: agent ────────────────────────────────────── */
/* AgentSpeakerPanel routing presentation fills the column */

/* ─── Right: content ─────────────────────────────────── */
.ds-right {
    display: grid;
    gap: 1.25rem;
}

/* ─── Headline ───────────────────────────────────────── */
.ds-eyebrow {
    font-size: 0.67rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--rd-primary-orange);
    opacity: 0.65;
    margin-bottom: 0.4rem;
}

.ds-title {
    font-size: clamp(1.85rem, 3.8vw, 2.7rem);
    font-weight: 900;
    line-height: 1.07;
    letter-spacing: -0.025em;
    color: var(--rd-text-main);
}

.ds-title-accent {
    color: var(--rd-primary-orange);
}

/* ─── Divider ────────────────────────────────────────── */
.ds-divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.ds-divider-line {
    flex: 1;
    height: 1.5px;
    background: var(--rd-frame-border);
    border-radius: 999px;
}

.ds-divider-text {
    font-size: 0.67rem;
    font-weight: 900;
    letter-spacing: 0.17em;
    text-transform: uppercase;
    color: var(--rd-text-muted);
    white-space: nowrap;
}

/* ─── Trait cards ─────────────────────────────────────── */
.ds-traits {
    display: grid;
    gap: 0.7rem;
}

.ds-trait {
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 2px solid var(--rd-story-border);
    border-radius: 22px;
    background: var(--rd-story-surface);
    box-shadow:
        0 5px 0 var(--rd-lip),
        0 7px 0 var(--rd-lip-dark),
        0 16px 22px -8px var(--rd-shadow);
    padding: 1rem 1.1rem 1.15rem;
}

.ds-trait-icon {
    display: grid;
    place-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    background: linear-gradient(135deg, rgba(245, 133, 73, 0.12), rgba(238, 193, 112, 0.12));
    border: 1.5px solid rgba(238, 193, 112, 0.38);
    color: var(--rd-primary-orange);
    flex-shrink: 0;
}

.ds-trait-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.ds-trait-label {
    font-size: 0.9rem;
    font-weight: 900;
    color: var(--rd-text-main);
    line-height: 1.2;
}

.ds-trait-desc {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--rd-text-muted);
    line-height: 1.3;
}

/* ─── Entrance animations ────────────────────────────── */
.ds-anim {
    animation: dsIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--ds-delay, 0ms);
}

@keyframes dsIn {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ─── Reduced motion ─────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .ds-anim {
        animation: none;
    }
}
</style>
