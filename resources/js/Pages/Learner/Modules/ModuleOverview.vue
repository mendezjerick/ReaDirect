<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { ArrowRight, BookOpen, PlayCircle, Sparkles } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';

const props = defineProps({
    module: Object,
    activityTypes: Array,
    firstActivityType: String,
    lessonBoxes: Array,
    purpose: String,
    guideMessage: String,
    guideLineKey: String,
    goodbyeMessage: String,
    resumeRoute: String,
    actionLabel: String,
});

const label = (value) => value.replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
const lessons = computed(() => props.lessonBoxes ?? []);
const moduleTitle = computed(() => props.module?.title ?? 'Module Overview');
const moduleDescription = computed(() => props.purpose ?? props.module?.description ?? 'Practice one reading skill at a time.');
const resolvedActionHref = computed(() => props.resumeRoute ?? (
    props.module?.key && props.firstActivityType
        ? `/learner/modules/${props.module.key}/activity/${props.firstActivityType}`
        : '/learner/dashboard'
));
const resolvedActionLabel = computed(() => props.actionLabel ?? 'Start Module');

const activeLessonKey = ref(null);
const guideMessage = ref(props.guideMessage ?? `Welcome to ${moduleTitle.value}. I will guide your practice one activity at a time.`);
const guideLineKey = ref(props.guideLineKey ?? '');
const guideState = ref('speaking');
const hoverTimer = ref(null);
const transitionTimer = ref(null);
const readyTimer = ref(null);
const hoverHistory = ref([]);
const hoverPausedUntil = ref(0);
const messageSequence = ref(0);
const transitionIndex = ref(0);

const transitionMessages = [
    { text: 'I see you found another lesson. I will explain this one clearly too.', lineKey: 'ciel.module_overview.transition.found_another' },
    { text: 'Let us look at this lesson next, so you know what to practice.', lineKey: 'ciel.module_overview.transition.look_next' },
    { text: 'You found a different practice box. I will tell you what it helps with.', lineKey: 'ciel.module_overview.transition.different_box' },
    { text: 'Good noticing. Here is the next lesson, and what you will do in it.', lineKey: 'ciel.module_overview.transition.next_one' },
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
    const text = typeof message === 'string' ? message : message?.text ?? '';
    const wordCount = text.trim().split(/\s+/).filter(Boolean).length;
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
    guideMessage.value = "Ready? Let's go slowly and give this one a try. I'll be right here with you.";
    guideLineKey.value = 'ciel.playful.go_slowly_try';

    readyTimer.value = window.setTimeout(() => {
        if (messageSequence.value !== sequence) return;

        guideMessage.value = "Let's try this one together. Look closely, smile a little, and say it when you're ready.";
        guideLineKey.value = 'ciel.playful.try_together_smile';
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
            guideMessage.value = transitionMessage.text;
            guideLineKey.value = transitionMessage.lineKey;
            transitionTimer.value = window.setTimeout(() => {
                if (messageSequence.value !== sequence || Date.now() < hoverPausedUntil.value) return;

                guideMessage.value = lesson.explanation;
                guideLineKey.value = lesson.line_key ?? '';
            }, transitionDelayFor(transitionMessage));
            return;
        }

        guideMessage.value = lesson.explanation;
        guideLineKey.value = lesson.line_key ?? '';
    }, 250);
};

onBeforeUnmount(() => {
    clearGuideTimers();
});
</script>

<template>
    <GuideLayout
        :progress="76"
        back-url="/learner/dashboard"
        back-label="Back to Learner Dashboard"
        agent-type="coach_feedback"
        :agent-state="guideState"
        :agent-message="guideMessage"
        :agent-line-key="guideLineKey"
        eyebrow="Module Overview"
        divider-label="Choose lesson"
        :primary-label="resolvedActionLabel"
        :primary-href="resolvedActionHref"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            {{ moduleTitle }}
        </template>

        <section class="module-overview-shell">
            <div class="guide-progress-card guide-anim module-overview-intro" style="--guide-delay: 200ms">
                <span class="guide-pill">
                    <BookOpen class="size-4" />
                    Practice plan
                </span>
                <p class="module-overview-purpose">{{ moduleDescription }}</p>
                <p class="module-overview-hint">Choose a lesson box. Miss Ciel will tell you what it means.</p>
            </div>

            <div class="module-lesson-panel" :class="{ 'module-lesson-panel-many': lessons.length > 4 }">
                <button
                    v-for="(lesson, index) in lessons"
                    :key="lesson.key"
                    type="button"
                    class="guide-trait guide-anim module-lesson-card"
                    :class="{ 'module-lesson-card--active': activeLessonKey === lesson.key }"
                    :style="`--guide-delay: ${285 + index * 45}ms`"
                    @mouseenter="explainLesson(lesson)"
                    @focus="explainLesson(lesson)"
                    @click="explainLesson(lesson)"
                >
                    <span class="guide-trait-icon guide-trait-icon--teal">
                        <PlayCircle class="size-5 stroke-[2.5]" />
                    </span>
                    <span class="guide-trait-body">
                        <span class="guide-trait-label">{{ lesson.title ?? label(lesson.key) }}</span>
                        <span class="guide-trait-desc">{{ lesson.description }}</span>
                    </span>
                    <span v-if="activeLessonKey === lesson.key" class="module-lesson-active-pill">
                        <Sparkles class="size-3.5" />
                        Ciel
                    </span>
                </button>
            </div>
        </section>
    </GuideLayout>
</template>

<style scoped>
.module-overview-shell {
    display: grid;
    gap: 1rem;
}

.module-overview-intro {
    align-items: start;
    padding: clamp(1.1rem, 3vw, 1.55rem);
}

.module-overview-purpose {
    color: var(--rd-text-main);
    font-size: clamp(1.05rem, 2.3vw, 1.45rem);
    font-weight: 900;
    line-height: 1.18;
}

.module-overview-hint {
    color: var(--rd-text-muted);
    font-size: 0.86rem;
    font-weight: 800;
    line-height: 1.35;
}

.module-lesson-panel {
    display: grid;
    gap: 0.8rem;
}

.module-lesson-card {
    position: relative;
    width: 100%;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.module-lesson-card:hover,
.module-lesson-card:focus-visible,
.module-lesson-card--active {
    border-color: var(--rd-primary-orange);
    outline: none;
    transform: translateY(-1px);
}

.module-lesson-card--active {
    background: rgba(245, 133, 73, 0.06);
}

.module-lesson-active-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    align-self: flex-start;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.12);
    padding: 0.32rem 0.55rem;
    color: var(--rd-primary-orange);
    font-size: 0.68rem;
    font-weight: 900;
    line-height: 1;
}

@media (min-width: 900px) {
    .module-lesson-panel {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .module-lesson-panel-many {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .module-lesson-card {
        align-items: flex-start;
    }
}
</style>
