<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Check, Volume2, RotateCcw } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import AgentSpeakerTTS from '../../Components/Agents/AgentSpeakerTTS.vue';
import AgentVideoPlayer from '../../Components/Agents/AgentVideoPlayer.vue';

const props = defineProps({
    stories: Array,
    assessmentAttemptId: Number,
});

const isSpeaking = ref(false);
const ttsKey = ref(0);

const replayMessage = () => {
    ttsKey.value += 1;
};

const form = useForm({
    assessment_attempt_id: props.assessmentAttemptId,
    passage_id: '',
});

const selectedStory = computed(() => (props.stories ?? []).find((s) => s.id === form.passage_id));

const chooseStory = (story) => { form.passage_id = story.id; };
const submit = () => { if (!form.passage_id) return; form.post('/learner/diagnostic/story-selection'); };
</script>

<template>
    <LearnerLayout :progress="76" diagnostic-step="sentence-reading" :has-bottom-bar="false">
        <!-- No #agent slot — landscape layout -->

        <div class="ss-landscape">

            <!-- LEFT: Miss Vivian -->
            <div class="ss-agent ss-anim flex flex-col items-center" style="--ss-delay: 0ms">
                <!-- Avatar -->
                <div class="ss-agent-avatar">
                    <AgentVideoPlayer
                        agent="assessment"
                        :action="isSpeaking ? 'talk' : 'idle'"
                        alt="Miss Vivian"
                        class="h-full w-full object-cover"
                    />
                </div>
                
                <p class="mt-5 text-center text-sm font-black uppercase tracking-widest text-primary">Miss Vivian</p>
                
                <div class="mt-2 flex items-center justify-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-black text-primary ring-1 ring-primary/20">
                        <Volume2 class="size-3.5" />
                        {{ isSpeaking ? 'Speaking' : 'Ready' }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-black text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-200 hover:text-slate-700"
                        @click="replayMessage"
                    >
                        <RotateCcw class="size-3.5" />
                        Replay
                    </button>
                </div>

                <div class="relative mt-4 w-full max-w-[280px] rounded-2xl border border-slate-200/60 bg-white p-4 shadow-sm text-center">
                    <span class="absolute left-1/2 top-0 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l border-t border-slate-200/60 bg-white" aria-hidden="true" />
                    <p class="text-[14.5px] font-bold text-slate-700">
                        Choose one story for your final reading passage.
                    </p>
                </div>

                <AgentSpeakerTTS
                    :key="ttsKey"
                    agent-type="assessment"
                    message="Choose one story for your final reading passage."
                    line-key="vivian.assessment.story_choice"
                    @speaking-start="isSpeaking = true"
                    @speaking-end="isSpeaking = false"
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

                <!-- CTA Button -->
                <div class="mt-4 ss-anim" style="--ss-delay: 200ms">
                    <button 
                        class="rd-cta-pill w-full"
                        :disabled="form.processing || !selectedStory"
                        @click="submit"
                        :class="{'opacity-50 cursor-not-allowed': form.processing || !selectedStory}"
                    >
                        Start story {{ selectedStory?.story_number ?? '' }}
                        <ArrowRight class="ml-2 size-6 stroke-[3]" />
                    </button>
                </div>
            </div>
        </div>

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
    min-height: calc(100vh - 130px);
    align-content: center;
}

@media (min-width: 768px) {
    .ss-landscape {
        grid-template-columns: 280px minmax(400px, 500px);
        justify-content: center;
        gap: 3.5rem;
        align-items: center;
    }
}

/* ─── Agent Column ──────────────────────────────────── */
.ss-agent-avatar {
    width: 230px;
    height: 230px;
    border-radius: 28px;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 12px 24px rgba(54, 83, 101, 0.12), 0 4px 8px rgba(54, 83, 101, 0.08);
    background: #f8fafc;
    flex-shrink: 0;
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

/* ─── CTA Pill Button ──────────────────────────────────── */
.rd-cta-pill {
    display: inline-flex;
    min-height: 64px;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: linear-gradient(180deg, #F58549 0%, #D9652F 100%);
    padding: 0 2.5rem;
    font-size: 1.1rem;
    font-weight: 900;
    letter-spacing: 0.1em;
    color: white;
    text-transform: uppercase;
    box-shadow: 0 8px 0 #B84B24, 0 12px 24px rgba(217, 101, 47, 0.3);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: none;
    cursor: pointer;
}
.rd-cta-pill:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 0 #B84B24, 0 16px 32px rgba(217, 101, 47, 0.4);
    filter: brightness(1.05);
}
.rd-cta-pill:not(:disabled):active {
    transform: translateY(6px);
    box-shadow: 0 2px 0 #B84B24, 0 4px 12px rgba(217, 101, 47, 0.2);
}
</style>
