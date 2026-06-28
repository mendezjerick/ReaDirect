<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Check } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({
    stories: Array,
    assessmentAttemptId: Number,
});

const form = useForm({
    assessment_attempt_id: props.assessmentAttemptId,
    passage_id: '',
});

const selectedStory = computed(() => (props.stories ?? []).find((s) => s.id === form.passage_id));

const chooseStory = (story) => { form.passage_id = story.id; };
const submit = () => { if (!form.passage_id) return; form.post('/learner/diagnostic/story-selection'); };
</script>

<template>
    <LearnerLayout :progress="76" diagnostic-step="sentence-reading">
        <!-- No #agent slot — landscape layout -->

        <div class="ss-landscape">

            <!-- LEFT: Miss Vivian -->
            <div class="ss-agent ss-anim" style="--ss-delay: 0ms">
                <AgentSpeakerPanel
                    agent-type="assessment"
                    state="speaking"
                    presentation="routing"
                    message="Choose one story for your final reading passage."
                    line-key="vivian.assessment.story_choice"
                    show-audio-button
                />
            </div>

            <!-- RIGHT: Story picker -->
            <div class="ss-content">

                <!-- Headline -->
                <header class="ss-headline ss-anim" style="--ss-delay: 60ms">
                    <p class="ss-eyebrow">Story Selection</p>
                    <h1 class="ss-title">Choose your <span class="ss-title-accent">story.</span></h1>
                </header>

                <!-- Story cards — always side by side -->
                <div class="ss-cards">
                    <button
                        v-for="(story, i) in stories"
                        :key="story.id"
                        type="button"
                        class="ss-card-btn ss-anim"
                        :style="`--ss-delay: ${130 + i * 85}ms`"
                        :class="{ 'ss-card-btn--selected': form.passage_id === story.id }"
                        @click="chooseStory(story)"
                    >
                        <!-- rd-card provides: warm amber frame + stacked shadow -->
                        <div class="ss-card rd-card">
                            <div class="ss-card-face rd-card__face">

                                <!-- Selected checkmark badge -->
                                <span
                                    class="ss-check"
                                    :class="{ 'ss-check--on': form.passage_id === story.id }"
                                    aria-hidden="true"
                                >
                                    <Check class="size-3.5 stroke-[3.5]" />
                                </span>

                                <!-- Story number circle -->
                                <span class="ss-badge">{{ story.story_number }}</span>

                                <!-- Story info -->
                                <div class="ss-story-body">
                                    <p class="ss-story-label">Story {{ story.story_number }}</p>
                                    <p class="ss-story-title">{{ story.title }}</p>
                                </div>

                                <!-- Tap cue -->
                                <p class="ss-tap-cue" :class="{ 'ss-tap-cue--active': form.passage_id === story.id }">
                                    {{ form.passage_id === story.id ? '✓ Selected' : 'Tap to select' }}
                                </p>
                            </div>
                        </div>
                    </button>
                </div>

                <!-- Validation error -->
                <p v-if="form.errors.passage_id" class="ss-error">
                    {{ form.errors.passage_id }}
                </p>
            </div>
        </div>

        <BottomActionBar>
            <PrimaryButton
                :disabled="form.processing || !selectedStory"
                class="gap-3 px-8 py-4 text-base"
                @click="submit"
            >
                Start story {{ selectedStory?.story_number ?? '' }}
                <ArrowRight class="size-5" />
            </PrimaryButton>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
/* ─── Landscape grid ─────────────────────────────────── */
.ss-landscape {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.75rem;
    max-width: 68rem;
    margin-inline: auto;
    padding-bottom: 2rem;
}

@media (min-width: 768px) {
    .ss-landscape {
        grid-template-columns: 1fr 1.1fr;
        gap: 2.5rem;
        align-items: center;
    }
}

/* ─── Content column ─────────────────────────────────── */
.ss-content {
    display: grid;
    gap: 1.1rem;
}

/* ─── Headline ───────────────────────────────────────── */
.ss-eyebrow {
    font-size: 0.67rem;
    font-weight: 900;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--rd-primary-orange);
    opacity: 0.65;
    margin-bottom: 0.35rem;
}

.ss-title {
    font-size: clamp(1.85rem, 3.8vw, 2.7rem);
    font-weight: 900;
    line-height: 1.07;
    letter-spacing: -0.025em;
    color: var(--rd-text-main);
}

.ss-title-accent {
    color: var(--rd-primary-orange);
}

/* ─── Story cards grid ────────────────────────────────── */
.ss-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

/* ─── Card button wrapper ─────────────────────────────── */
.ss-card-btn {
    display: block;
    width: 100%;
    padding: 0;
    border: none;
    background: none;
    cursor: pointer;
    text-align: left;
    border-radius: var(--rd-radius-frame);
    transition: transform 0.15s ease;
}

.ss-card-btn:hover:not(.ss-card-btn--selected) {
    transform: translateY(-2px);
}

.ss-card-btn:active {
    transform: translateY(0);
}

/* ─── rd-card border override on selection ────────────── */
/* .ss-card and .ss-card-face have scoped attributes so these work */
.ss-card {
    transition: border-color 0.2s ease;
}

.ss-card-btn--selected .ss-card {
    border-color: var(--rd-primary-orange) !important;
}

.ss-card-btn--selected .ss-card-face {
    background: rgba(245, 133, 73, 0.04);
}

.ss-card-btn:not(.ss-card-btn--selected):hover .ss-card {
    border-color: rgba(245, 133, 73, 0.45);
}

/* Ghost checkmark preview on hover */
.ss-card-btn:not(.ss-card-btn--selected):hover .ss-check {
    opacity: 0.22;
    transform: scale(1);
}

/* ─── Card face layout ────────────────────────────────── */
/* rd-card__face: parchment surface, inner border, 18px radius */
.ss-card-face {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1.1rem 1rem 1.3rem;
    min-height: 11rem;
    transition: background 0.2s ease;
}

/* ─── Check badge ─────────────────────────────────────── */
.ss-check {
    position: absolute;
    top: 0.7rem;
    right: 0.7rem;
    display: grid;
    place-items: center;
    width: 1.4rem;
    height: 1.4rem;
    border-radius: 50%;
    background: var(--rd-primary-orange);
    color: #fff;
    opacity: 0;
    transform: scale(0.6);
    transition:
        opacity 0.2s ease,
        transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.ss-check--on {
    opacity: 1;
    transform: scale(1);
}

/* ─── Story number circle ─────────────────────────────── */
.ss-badge {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--rd-primary-orange) 0%, #E0732E 100%);
    color: #fff;
    font-size: 1.15rem;
    font-weight: 900;
    line-height: 1;
    box-shadow: 0 3px 0 rgba(190, 75, 15, 0.28), 0 6px 12px rgba(245, 133, 73, 0.2);
    flex-shrink: 0;
}

/* ─── Story info ──────────────────────────────────────── */
.ss-story-body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.ss-story-label {
    font-size: 0.62rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--rd-text-muted);
}

.ss-story-title {
    font-size: 0.96rem;
    font-weight: 900;
    line-height: 1.25;
    color: var(--rd-text-main);
}

/* ─── Tap cue ─────────────────────────────────────────── */
.ss-tap-cue {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--rd-text-muted);
    opacity: 0.55;
    transition: color 0.2s ease, opacity 0.2s ease;
    margin-top: auto;
}

.ss-tap-cue--active {
    color: var(--rd-primary-orange);
    opacity: 1;
}

/* ─── Validation error ────────────────────────────────── */
.ss-error {
    padding: 0.75rem 1rem;
    border-radius: 14px;
    background: #fff1f2;
    border: 1px solid rgba(248, 113, 113, 0.3);
    font-size: 0.8rem;
    font-weight: 700;
    color: #dc2626;
}

/* ─── Shared entrance ────────────────────────────────── */
.ss-anim {
    animation: ssIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--ss-delay, 0ms);
}

@keyframes ssIn {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ─── Reduced motion ─────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .ss-anim,
    .ss-card-btn,
    .ss-check,
    .ss-tap-cue {
        animation: none;
        transition: none;
    }
}
</style>
