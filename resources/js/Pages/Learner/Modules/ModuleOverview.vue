<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../../Layouts/LearnerLayout.vue';
import AgentSpeakerPanel from '../../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import SecondaryButton from '../../../Components/SecondaryButton.vue';
import BottomActionBar from '../../../Components/BottomActionBar.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';

const props = defineProps({
    module: Object,
    activityTypes: Array,
    firstActivityType: String,
    lessonBoxes: Array,
    purpose: String,
    guideMessage: String,
    goodbyeMessage: String,
    resumeRoute: String,
    actionLabel: String,
});

const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const activeLessonKey = ref(null);
const guideMessage = ref(props.guideMessage ?? `Welcome to ${props.module.title}. I will guide your practice one activity at a time.`);
const guideState = ref('speaking');
const hoverTimer = ref(null);
const transitionTimer = ref(null);
const readyTimer = ref(null);
const returning = ref(false);
const hoverHistory = ref([]);
const hoverPausedUntil = ref(0);
const messageSequence = ref(0);
const transitionIndex = ref(0);

const transitionMessages = [
    'I see you found another lesson.',
    'Let us look at this lesson next.',
    'You found a different practice box.',
    'Good noticing. Here is the next one.',
];

const stopAgentSpeech = () => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
};

const clearGuideTimers = () => {
    window.clearTimeout(hoverTimer.value);
    window.clearTimeout(transitionTimer.value);
    window.clearTimeout(readyTimer.value);
};

const transitionDelayFor = (message) => {
    const wordCount = message.trim().split(/\s+/).filter(Boolean).length;
    return Math.min(Math.max(2800, wordCount * 360), 4600);
};

const nextTransitionMessage = () => {
    const message = transitionMessages[transitionIndex.value % transitionMessages.length];
    transitionIndex.value += 1;

    return message;
};

const isHoverSpam = () => {
    const now = Date.now();
    hoverHistory.value = hoverHistory.value.filter((time) => now - time < 3200);
    hoverHistory.value.push(now);

    return hoverHistory.value.length >= 4;
};

const pauseForHoverSpam = () => {
    const sequence = messageSequence.value + 1;
    messageSequence.value = sequence;
    hoverPausedUntil.value = Date.now() + 4600;
    hoverHistory.value = [];
    clearGuideTimers();
    stopAgentSpeech();
    guideState.value = 'encouraging';
    guideMessage.value = 'Let us slow down and choose one lesson at a time.';

    readyTimer.value = window.setTimeout(() => {
        if (messageSequence.value !== sequence) return;

        guideMessage.value = 'Are you ready to choose one lesson without rushing?';
        guideState.value = 'speaking';
    }, 3000);
};

const explainLesson = (lesson) => {
    if (!lesson || lesson.key === activeLessonKey.value) return;
    if (Date.now() < hoverPausedUntil.value) return;

    window.clearTimeout(hoverTimer.value);
    hoverTimer.value = window.setTimeout(() => {
        const switched = Boolean(activeLessonKey.value);
        const sequence = messageSequence.value + 1;

        if (switched && isHoverSpam()) {
            pauseForHoverSpam();
            return;
        }

        messageSequence.value = sequence;
        activeLessonKey.value = lesson.key;
        window.clearTimeout(transitionTimer.value);
        window.clearTimeout(readyTimer.value);
        stopAgentSpeech();
        guideState.value = 'speaking';

        if (switched) {
            const transitionMessage = nextTransitionMessage();
            guideMessage.value = transitionMessage;
            transitionTimer.value = window.setTimeout(() => {
                if (messageSequence.value !== sequence || Date.now() < hoverPausedUntil.value) return;

                guideMessage.value = lesson.explanation;
            }, transitionDelayFor(transitionMessage));
            return;
        }

        guideMessage.value = lesson.explanation;
    }, 250);
};

const returnToDashboard = () => {
    if (returning.value) return;
    returning.value = true;
    stopAgentSpeech();
    guideMessage.value = props.goodbyeMessage ?? 'See you next time!';
    guideState.value = 'happy';
    window.setTimeout(() => {
        window.location.href = '/learner/dashboard';
    }, 1200);
};

onBeforeUnmount(() => {
    clearGuideTimers();
});
</script>

<template>
    <LearnerLayout :progress="76">
        <template #agent>
            <AgentSpeakerPanel agent-type="coach_feedback" :state="guideState" :message="guideMessage" />
        </template>

        <section class="module-overview-shell mx-auto grid w-full gap-4">
            <div class="module-overview-card rounded-[28px] border border-border bg-surface p-5 shadow-xl shadow-primary/10">
                <div>
                    <StatusBadge status="Module Overview" variant="primary" />
                    <h1 class="module-overview-title mt-3 font-black text-text">{{ module.title }}</h1>
                    <p class="module-overview-purpose mt-3 font-bold leading-relaxed text-muted">{{ purpose ?? module.description }}</p>
                </div>
                <p class="module-overview-hint rounded-2xl bg-primaryLight px-4 py-3 font-black text-primaryDark">
                    Choose a lesson box. Miss Ciel will tell you what it means.
                </p>
            </div>

            <div class="module-lesson-panel grid min-h-0 gap-3" :class="{ 'module-lesson-panel-many': lessonBoxes.length > 4 }">
                <button
                    v-for="lesson in lessonBoxes"
                    :key="lesson.key"
                    type="button"
                    class="module-lesson-card group rounded-[24px] border-2 bg-surface p-4 text-left shadow-lg shadow-primary/5 transition hover:-translate-y-0.5 hover:border-primary focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/20"
                    :class="activeLessonKey === lesson.key ? 'border-primary bg-primaryLight/40' : 'border-border'"
                    @mouseenter="explainLesson(lesson)"
                    @focus="explainLesson(lesson)"
                    @click="explainLesson(lesson)"
                >
                    <div>
                        <p class="module-lesson-title font-black text-text">{{ lesson.title ?? label(lesson.key) }}</p>
                        <p class="module-lesson-description mt-2 font-bold text-muted">{{ lesson.description }}</p>
                    </div>
                </button>
            </div>
        </section>

        <BottomActionBar>
            <div class="flex w-full items-center justify-between gap-3">
                <SecondaryButton :disabled="returning" @click="returnToDashboard">Back to Learner Dashboard</SecondaryButton>
                <Link v-if="resumeRoute || firstActivityType" :href="resumeRoute ?? `/learner/modules/${module.key}/activity/${firstActivityType}`">
                    <PrimaryButton>{{ actionLabel ?? 'Start Module' }}</PrimaryButton>
                </Link>
            </div>
        </BottomActionBar>
    </LearnerLayout>
</template>

<style scoped>
.module-overview-shell {
    max-width: min(100%, 74rem);
}

.module-overview-card {
    container-type: inline-size;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    justify-content: space-between;
}

.module-overview-title {
    font-size: clamp(2rem, 4.6vw, 4.25rem);
    font-size: clamp(2rem, 6.6cqi, 4.25rem);
    line-height: 0.98;
}

.module-overview-purpose {
    font-size: clamp(1.1rem, 1.8vw, 1.6rem);
    font-size: clamp(1.1rem, 2.1cqi, 1.6rem);
}

.module-overview-hint {
    font-size: clamp(0.95rem, 1.2vw, 1.3rem);
    font-size: clamp(0.95rem, 1.4cqi, 1.3rem);
    line-height: 1.45;
}

.module-lesson-card {
    container-type: inline-size;
    min-height: 9rem;
}

.module-lesson-title {
    font-size: clamp(1.3rem, 2.25vw, 2.25rem);
    font-size: clamp(1.3rem, 5.1cqi, 2.25rem);
    line-height: 1.1;
}

.module-lesson-description {
    font-size: clamp(0.98rem, 1.45vw, 1.45rem);
    font-size: clamp(0.98rem, 3.1cqi, 1.45rem);
    line-height: 1.35;
}

@media (min-width: 1024px) {
    .module-overview-shell {
        min-height: clamp(30rem, calc(100svh - 13rem), 48rem);
        grid-template-rows: auto minmax(0, 1fr);
    }

    .module-overview-card {
        align-items: center;
        flex-direction: row;
        gap: clamp(1.5rem, 3vw, 3rem);
        min-height: clamp(11rem, 20svh, 15rem);
    }

    .module-overview-card > :first-child {
        min-width: 0;
        flex: 1 1 auto;
    }

    .module-overview-card > p {
        flex: 0 1 clamp(18rem, 32%, 26rem);
    }

    .module-lesson-panel {
        height: 100%;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-auto-rows: minmax(clamp(8rem, 16svh, 12rem), 1fr);
    }

    .module-lesson-panel-many {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-auto-rows: minmax(clamp(7rem, 13svh, 9.5rem), 1fr);
    }

    .module-lesson-card {
        min-height: 0;
    }
}

@media (min-width: 1536px) {
    .module-overview-shell {
        max-width: min(100%, 86rem);
        min-height: clamp(34rem, calc(100svh - 13rem), 56rem);
    }

    .module-lesson-panel {
        grid-auto-rows: minmax(clamp(9rem, 17svh, 13rem), 1fr);
    }

    .module-lesson-panel-many {
        grid-auto-rows: minmax(clamp(7.5rem, 13svh, 10.5rem), 1fr);
    }
}

@media (min-width: 1024px) and (max-height: 780px) {
    .module-overview-shell {
        min-height: calc(100svh - 10.5rem);
    }

    .module-lesson-panel {
        grid-auto-rows: minmax(7.25rem, 1fr);
    }

    .module-lesson-title {
        font-size: clamp(1.1rem, 1.9vw, 1.55rem);
        font-size: clamp(1.1rem, 4.25cqi, 1.55rem);
    }

    .module-lesson-description {
        font-size: clamp(0.86rem, 1.25vw, 1.1rem);
        font-size: clamp(0.86rem, 2.65cqi, 1.1rem);
        line-height: 1.28;
    }
}
</style>
