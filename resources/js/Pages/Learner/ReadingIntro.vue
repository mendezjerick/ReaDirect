<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Mic, HelpCircle } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';
</script>

<template>
    <LearnerLayout :progress="70" diagnostic-step="sentence-reading">
        <!-- No #agent slot — landscape layout, full control -->

        <div class="ri-landscape">

            <!-- LEFT: Miss Vivian -->
            <div class="ri-agent ri-anim" style="--ri-delay: 0ms">
                <AgentSpeakerPanel
                    agent-type="assessment"
                    state="speaking"
                    presentation="routing"
                    message="Choose one story for your final reading passage."
                    line-key="vivian.assessment.story_choice"
                    show-audio-button
                />
            </div>

            <!-- RIGHT: Assignment -->
            <div class="ri-content">

                <!-- Headline -->
                <header class="ri-headline ri-anim" style="--ri-delay: 60ms">
                    <p class="ri-eyebrow">Reading Passage</p>
                    <h1 class="ri-title">
                        Your reading<br><span class="ri-title-accent">turn.</span>
                    </h1>
                </header>

                <!-- Reading assignment ticket — THE SIGNATURE ELEMENT -->
                <div class="ri-ticket rd-card">
                    <div class="ri-ticket-face rd-card__face">

                        <!-- Teal header band -->
                        <div class="ri-ticket-header">
                            <p class="ri-ticket-label">Reading assignment</p>
                            <span class="ri-ticket-ornament" aria-hidden="true">✦</span>
                        </div>

                        <!-- 3 steps -->
                        <div class="ri-steps">

                            <div class="ri-step">
                                <span class="ri-step-icon ri-step-icon--orange">
                                    <BookOpen class="size-[1.05rem]" />
                                </span>
                                <div class="ri-step-body">
                                    <p class="ri-step-title">Pick a story</p>
                                    <p class="ri-step-desc">Choose the one you want to read.</p>
                                </div>
                            </div>

                            <div class="ri-step-rule" aria-hidden="true" />

                            <div class="ri-step">
                                <span class="ri-step-icon ri-step-icon--teal">
                                    <Mic class="size-[1.05rem]" />
                                </span>
                                <div class="ri-step-body">
                                    <p class="ri-step-title">Read it aloud</p>
                                    <p class="ri-step-desc">Speak clearly into the microphone.</p>
                                </div>
                            </div>

                            <div class="ri-step-rule" aria-hidden="true" />

                            <div class="ri-step">
                                <span class="ri-step-icon ri-step-icon--violet">
                                    <HelpCircle class="size-[1.05rem]" />
                                </span>
                                <div class="ri-step-body">
                                    <p class="ri-step-title">Answer 5 questions</p>
                                    <p class="ri-step-desc">Tell me what you understood.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <BottomActionBar>
            <Link href="/learner/diagnostic/story-selection">
                <PrimaryButton class="gap-3 px-8 py-4 text-base">
                    Choose a story
                    <ArrowRight class="size-5" />
                </PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* ─── Landscape grid ─────────────────────────────────── */
.ri-landscape {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.75rem;
    max-width: 68rem;
    margin-inline: auto;
    padding-bottom: 2rem;
}

@media (min-width: 768px) {
    .ri-landscape {
        grid-template-columns: 1fr 1.1fr;
        gap: 2.5rem;
        align-items: center;
    }
}

/* ─── Content column ─────────────────────────────────── */
.ri-content {
    display: grid;
    gap: 1.1rem;
}

/* ─── Headline ───────────────────────────────────────── */
.ri-eyebrow {
    font-size: 0.67rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--rd-primary-orange);
    opacity: 0.65;
    margin-bottom: 0.35rem;
}

.ri-title {
    font-size: clamp(1.85rem, 3.8vw, 2.7rem);
    font-weight: 900;
    line-height: 1.05;
    letter-spacing: -0.025em;
    color: var(--rd-text-main);
}

.ri-title-accent {
    color: var(--rd-primary-orange);
}

/* ─── Assignment ticket ───────────────────────────────── */
/* rd-card provides: warm amber frame, stacked shadow, 28px radius */
.ri-ticket {
    animation: riTicket 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    animation-delay: 140ms;
}

@keyframes riTicket {
    from { opacity: 0; transform: translateY(12px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* rd-card__face: parchment interior */
.ri-ticket-face {
    overflow: hidden;
    padding: 0;
}

/* Deep teal header band — the signature element */
.ri-ticket-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.8rem 1.25rem;
    background: linear-gradient(135deg, #365365 0%, #2A4557 100%);
}

.ri-ticket-label {
    font-size: 0.63rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(238, 193, 112, 0.85);
}

.ri-ticket-ornament {
    font-size: 0.75rem;
    color: rgba(238, 193, 112, 0.4);
    line-height: 1;
}

/* ─── Steps ──────────────────────────────────────────── */
.ri-steps {
    padding: 1.1rem 1.25rem 1.4rem;
    display: grid;
    gap: 0;
}

.ri-step {
    display: flex;
    align-items: center;
    gap: 0.95rem;
    padding: 0.75rem 0;
}

.ri-step-rule {
    height: 1px;
    background: var(--rd-face-border);
}

.ri-step-icon {
    display: grid;
    place-items: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 0.55rem;
    flex-shrink: 0;
    border: 1.5px solid transparent;
}

.ri-step-icon--orange {
    background: linear-gradient(135deg, rgba(245, 133, 73, 0.14), rgba(238, 193, 112, 0.1));
    border-color: rgba(238, 193, 112, 0.38);
    color: var(--rd-primary-orange);
}

.ri-step-icon--teal {
    background: linear-gradient(135deg, rgba(54, 83, 101, 0.12), rgba(42, 69, 87, 0.08));
    border-color: rgba(54, 83, 101, 0.22);
    color: #365365;
}

.ri-step-icon--violet {
    background: linear-gradient(135deg, rgba(167, 139, 250, 0.14), rgba(139, 92, 246, 0.08));
    border-color: rgba(167, 139, 250, 0.25);
    color: #7c3aed;
}

.ri-step-body {
    min-width: 0;
}

.ri-step-title {
    font-size: 0.92rem;
    font-weight: 900;
    color: var(--rd-text-main);
    line-height: 1.2;
}

.ri-step-desc {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--rd-text-muted);
    margin-top: 0.1rem;
    line-height: 1.3;
}

/* ─── Shared entrance ────────────────────────────────── */
.ri-anim {
    animation: riIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--ri-delay, 0ms);
}

@keyframes riIn {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Reduced motion ─────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .ri-anim,
    .ri-ticket {
        animation: none;
    }
}
</style>
