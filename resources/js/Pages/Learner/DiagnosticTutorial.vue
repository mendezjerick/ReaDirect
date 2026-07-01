<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { AudioWaveform, Mic, MousePointer2, Play } from 'lucide-vue-next';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import AssessmentTaskWorkspace from '../../Components/Learner/AssessmentTaskWorkspace.vue';
import AssessmentPromptText from '../../Components/Learner/AssessmentPromptText.vue';
import AssessmentCircleButton from '../../Components/Learner/AssessmentCircleButton.vue';
import { RESULT_TONE_ASSESSMENT } from '../../utils/assessmentDisplay';

const props = defineProps({
    demoItem: { type: Object, required: true },
    completeUrl: { type: String, required: true },
    nextUrl: { type: String, required: true },
});

const tutorialLines = {
    intro: 'Welcome. Before we begin the diagnostic activity, I will guide you through a short tutorial so you know exactly what to do. I will show the important parts of the page, and demonstrate how to record, listen, retry, and submit your answer. When you are ready to begin, click the Start button.',
    vivianLocation: 'This is where we are located. You will see me here while I guide you through the activity, and you will also meet my fellow teachers along the way. We are here to give instructions, help you understand what to do, remind you to take your time, and make the speaking activity easier to follow from the beginning to the end.',
    dialogueBox: 'This box is the dialogue box. This is where the instructions will appear while you are answering. It also shows the words that your teachers are saying, so if you need to read the instruction again, you can look here, follow what is written, and check what you should do before you record.',
    itemDisplay: 'This is the item display area. This is where the letter, word, or sentence that you need to say will appear. For this tutorial, our example word is Sun. When you see an item here, read it carefully first, look for the part you need to say, and only record when you feel ready.',
    recorder: 'This button is the recorder button. This is where you record your answer. When it is your turn, hold the button, say the item clearly, and then release the button when you are done speaking. Try to speak loudly enough, keep your voice steady, and avoid background noise as much as you can.',
    demonstrationSetup: 'I will demonstrate it for you now. Watch the recorder button carefully. I will hold it down, say the word that appears on the screen, and then release the button after I finish speaking. This is the same process you will do during the activity, so follow the movement slowly and notice each step.',
    demoWord: 'Sun.',
    playback: 'After recording, you can click the recorder button again to listen to your own recording. This helps you check if your voice was captured clearly. If the recording sounds good and you are happy with your answer, then you can continue to the next step with confidence and without rushing.',
    retry: 'If you are not satisfied with your recording, you can click Retry and record again. Use this when your voice was too soft, when there was noise, or when you feel that you did not say the item clearly. You can try again before submitting, so it is fine to pause and make another recording.',
    submit: 'When you are ready and you are satisfied with your recording, this is the button you will click to submit your audio. Submitting means the system will listen to your recording and check what it heard, so make sure your answer is clear, your voice is finished, and you feel ready before clicking it.',
    transcriptResult: 'After submitting, this same display area will show what the system heard you say. This is also where we will base if your answer is correct or not. Look at this part carefully, because it helps show whether the spoken answer matched the item and whether you can move forward.',
    manualOverride: 'As for my fellow teachers, you can use this manual transcript area if our system is having trouble recognizing the audio. If the learner said the answer correctly but the system heard something different, you can type what the learner actually said here, so the review can match the real spoken answer.',
    nextButton: 'After the answer has been checked, this button will let you move to the next item. Click it only when you are ready to continue. Each item follows the same process, so remember to read, record, listen if needed, retry if needed, and submit before going on.',
    farewell: 'That is the tutorial. Now you know how to use the speaking activity page and what each part is for. Take your time, speak clearly, and do your best. When the next page begins, remember that each item follows this same calm routine. I believe in you, and I know you can do it.',
};

// This timeline simulates the ASR activity UI only. It must not upload audio,
// create attempts, submit scoring data, or change real diagnostic behavior.
const tutorialScript = [
    { key: 'vivianLocation', lineKey: 'vivian.tutorial.stage1.vivian_location', target: 'vivian', durationMs: 17500 },
    { key: 'dialogueBox', lineKey: 'vivian.tutorial.stage1.dialogue_box', target: 'dialogue', durationMs: 17000 },
    { key: 'itemDisplay', lineKey: 'vivian.tutorial.stage1.item_display', target: 'item', durationMs: 17000 },
    { key: 'recorder', lineKey: 'vivian.tutorial.stage1.recorder', target: 'recorder', durationMs: 17000 },
    { key: 'demonstrationSetup', lineKey: 'vivian.tutorial.stage1.demonstration_setup', target: 'recorder', durationMs: 17000 },
    { key: 'demoWord', lineKey: 'vivian.tutorial.stage1.demo_word', target: 'recorder', durationMs: 1700, action: 'record' },
    { key: 'playback', lineKey: 'vivian.tutorial.stage1.playback', target: 'recorder', durationMs: 17000 },
    { key: 'playbackClick', lineKey: 'vivian.tutorial.stage1.demo_word', target: 'recorder', durationMs: 1800, action: 'playback', hiddenFromProgress: true },
    { key: 'retry', lineKey: 'vivian.tutorial.stage1.retry', target: 'retry', durationMs: 17000 },
    { key: 'submit', lineKey: 'vivian.tutorial.stage1.submit', target: 'submit', durationMs: 17500 },
    { key: 'transcriptResult', lineKey: 'vivian.tutorial.stage1.transcript_result', target: 'item', durationMs: 17000, action: 'showResult' },
    { key: 'manualOverride', lineKey: 'vivian.tutorial.stage1.manual_override', target: 'manual', durationMs: 17000 },
    { key: 'nextButton', lineKey: 'vivian.tutorial.stage1.next_button', target: 'next', durationMs: 17000, action: 'showNext' },
    { key: 'farewell', lineKey: 'vivian.tutorial.stage1.farewell', target: 'vivian', durationMs: 17000 },
];

const targetSelectors = {
    vivian: '.assessment-agent-card',
    dialogue: '.assessment-agent-dialogue',
    item: '[data-tutorial-target="item-display"]',
    recorder: '[data-tutorial-target="recorder-button"]',
    retry: '[data-tutorial-target="retry-text"]',
    submit: '.assessment-primary-action',
    manual: '[data-tutorial-target="manual-transcript"]',
    next: '.assessment-primary-action',
};

const bodyClass = 'diagnostic-tutorial-active';
const hasStarted = ref(false);
const isComplete = ref(false);
const currentStepIndex = ref(-1);
const agentSpeaking = ref(false);
const recorderMode = ref('ready');
const hasDemoRecording = ref(false);
const hasPlayedRecording = ref(false);
const displayState = ref('item');
const primaryMode = ref('start');
const cursorVisible = ref(false);
const cursorPressed = ref(false);
const cursorPosition = ref({ x: 0, y: 0 });
const activeTimers = [];
const demoAudioUrl = ref('');
const demoAudio = ref(null);

const currentStep = computed(() => tutorialScript[currentStepIndex.value] ?? null);
const agentMessage = computed(() => (
    hasStarted.value && currentStep.value
        ? tutorialLines[currentStep.value.key] || tutorialLines.demoWord
        : tutorialLines.intro
));
const agentLineKey = computed(() => (
    hasStarted.value && currentStep.value
        ? currentStep.value.lineKey
        : 'vivian.tutorial.stage1.intro'
));
const agentIntent = computed(() => agentLineKey.value.endsWith('.demo_word') ? 'happy_praise' : 'focused_instruction');
const agentState = computed(() => {
    if (recorderMode.value === 'recording') return 'listening';
    if (isComplete.value) return 'happy';

    return hasStarted.value ? 'speaking' : 'listening';
});
const primaryLabel = computed(() => {
    if (isComplete.value) return 'Let\u2019s Go!';
    if (!hasStarted.value) return 'Start';
    if (primaryMode.value === 'next') return 'Next';

    return 'Submit';
});
const progress = computed(() => {
    if (!hasStarted.value) return 0;
    if (isComplete.value) return 100;

    const visibleSteps = tutorialScript.filter((step) => !step.hiddenFromProgress);
    const currentVisibleIndex = visibleSteps.findIndex((step) => step === currentStep.value);

    return Math.max(8, Math.round(((currentVisibleIndex + 1) / visibleSteps.length) * 100));
});
const taskTwoBTutorialPrompt = 'The sun is hot.';
const legacyTaskTwoBTutorialPrompts = new Set(['We sit in the sun.']);
const demoPrompt = computed(() => {
    const prompt = props.demoItem?.prompt?.trim();

    if (!prompt || legacyTaskTwoBTutorialPrompts.has(prompt)) {
        return taskTwoBTutorialPrompt;
    }

    return prompt;
});
const targetWord = computed(() => props.demoItem?.payload?.target_word ?? props.demoItem?.payload?.expected_answer ?? 'sun');
const targetWordDisplay = computed(() => {
    const word = String(targetWord.value || 'sun');

    return word.charAt(0).toUpperCase() + word.slice(1);
});
const showRetryText = computed(() => hasPlayedRecording.value || ['retry', 'submit', 'transcriptResult', 'manualOverride', 'nextButton', 'farewell'].includes(currentStep.value?.key));
const recorderLabel = computed(() => {
    if (recorderMode.value === 'recording') return 'Recording...';
    if (recorderMode.value === 'playing') return 'Playing...';
    if (hasDemoRecording.value) return 'Click to play audio';

    return 'Hold to record';
});

const setAgentSpeaking = (isSpeaking) => {
    agentSpeaking.value = isSpeaking === true;
};

const schedule = (callback, delay) => {
    const timer = window.setTimeout(callback, delay);
    activeTimers.push(timer);

    return timer;
};

const clearTimers = () => {
    while (activeTimers.length) {
        window.clearTimeout(activeTimers.pop());
    }
};

const blockLockedKeys = (event) => {
    if (!hasStarted.value || isComplete.value) return;

    event.preventDefault();
    event.stopPropagation();
};

const moveCursorTo = async (target) => {
    await nextTick();

    const selector = targetSelectors[target] ?? targetSelectors.item;
    const element = document.querySelector(selector);
    if (!element) return;

    const rect = element.getBoundingClientRect();
    const offsets = {
        vivian: { x: 0.62, y: 0.46 },
        dialogue: { x: 0.35, y: 0.48 },
        item: { x: 0.52, y: 0.48 },
        recorder: { x: 0.52, y: 0.42 },
        retry: { x: 0.5, y: 0.56 },
        submit: { x: 0.5, y: 0.5 },
        manual: { x: 0.68, y: 0.5 },
        next: { x: 0.5, y: 0.5 },
    }[target] ?? { x: 0.5, y: 0.5 };

    cursorPosition.value = {
        x: rect.left + (rect.width * offsets.x),
        y: rect.top + (rect.height * offsets.y),
    };
};

const loadDemoAudio = async () => {
    if (demoAudioUrl.value) return demoAudioUrl.value;

    try {
        const response = await fetch('/agent-voice/synthesize', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                agent: 'assessment',
                text: tutorialLines.demoWord,
                intent: 'happy_praise',
                line_key: 'vivian.tutorial.stage1.demo_word',
            }),
        });
        const payload = response.ok ? await response.json() : null;
        demoAudioUrl.value = payload?.audio_url ?? '';
    } catch {
        demoAudioUrl.value = '';
    }

    return demoAudioUrl.value;
};

const playDemoAudio = async () => {
    const audioUrl = await loadDemoAudio();
    if (!audioUrl || !demoAudio.value) return;

    demoAudio.value.currentTime = 0;
    try {
        await demoAudio.value.play();
    } catch {
        recorderMode.value = 'ready';
    }
};

const performStepAction = async (step) => {
    if (step.action === 'record') {
        primaryMode.value = 'submit';
        recorderMode.value = 'recording';
        cursorPressed.value = true;
        schedule(() => {
            cursorPressed.value = false;
            recorderMode.value = 'ready';
            hasDemoRecording.value = true;
        }, 1250);
        return;
    }

    if (step.action === 'playback') {
        recorderMode.value = 'playing';
        cursorPressed.value = true;
        await playDemoAudio();
        schedule(() => {
            cursorPressed.value = false;
            recorderMode.value = 'ready';
            hasPlayedRecording.value = true;
        }, 950);
        return;
    }

    if (step.action === 'showResult') {
        displayState.value = 'result';
        return;
    }

    if (step.action === 'showNext') {
        primaryMode.value = 'next';
    }
};

const runStep = async (index) => {
    const step = tutorialScript[index];
    if (!step) {
        isComplete.value = true;
        cursorVisible.value = false;
        primaryMode.value = 'next';
        return;
    }

    currentStepIndex.value = index;
    await moveCursorTo(step.target);
    await performStepAction(step);

    schedule(() => runStep(index + 1), step.durationMs);
};

const startTutorial = async () => {
    if (hasStarted.value) return;

    hasStarted.value = true;
    isComplete.value = false;
    currentStepIndex.value = -1;
    recorderMode.value = 'ready';
    hasDemoRecording.value = false;
    hasPlayedRecording.value = false;
    displayState.value = 'item';
    primaryMode.value = 'submit';
    cursorVisible.value = true;
    await loadDemoAudio();
    await runStep(0);
};

const completeTutorial = () => {
    if (!isComplete.value) return;

    router.post(props.completeUrl, {}, {
        preserveScroll: false,
        replace: true,
        onError: () => {
            window.location.href = props.nextUrl;
        },
    });
};

const handlePrimary = () => {
    if (!hasStarted.value) {
        startTutorial();
        return;
    }

    completeTutorial();
};

const handleResize = () => {
    if (cursorVisible.value && currentStep.value) {
        moveCursorTo(currentStep.value.target);
    }
};

onMounted(() => {
    document.body.classList.add(bodyClass);
    window.addEventListener('keydown', blockLockedKeys, true);
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    document.body.classList.remove(bodyClass);
    window.removeEventListener('keydown', blockLockedKeys, true);
    window.removeEventListener('resize', handleResize);
    clearTimers();
    demoAudio.value?.pause();
});
</script>

<template>
    <LearnerLayout assessment-task>
        <div
            class="diagnostic-tutorial-page"
            :class="{
                'diagnostic-tutorial-page--running': hasStarted && !isComplete,
                'diagnostic-tutorial-page--complete': isComplete,
            }"
        >
            <AssessmentTaskWorkspace
                agent-type="assessment"
                :agent-state="agentState"
                :agent-message="agentMessage"
                :agent-intent="agentIntent"
                :agent-line-key="agentLineKey"
                :progress="progress"
                :primary-label="primaryLabel"
                :primary-disabled="false"
                :display-state="displayState"
                @primary="handlePrimary"
                @agent-speaking-change="setAgentSpeaking"
            >
                <template #prompt>
                    <div data-tutorial-target="item-display" class="tutorial-item-display">
                        <AssessmentPromptText
                            :prompt="demoPrompt"
                            label="Read the highlighted word"
                            :highlight-targets="[{ text: targetWord, wholeWord: false }]"
                            size="sentence"
                        />
                    </div>
                </template>

                <template #result>
                    <div data-tutorial-target="item-display" class="tutorial-item-display">
                        <AssessmentPromptText
                            :prompt="targetWordDisplay"
                            size="word"
                            :tone="RESULT_TONE_ASSESSMENT"
                        />
                    </div>
                </template>

                <template #recorder>
                    <div class="tutorial-recorder-shell" data-tutorial-target="recorder">
                        <div class="tutorial-recorder-face">
                            <div class="tutorial-recorder-group">
                                <div data-tutorial-target="recorder-button">
                                    <AssessmentCircleButton
                                        :recording="recorderMode === 'recording'"
                                        :pulse="agentSpeaking || recorderMode === 'playing'"
                                        aria-label="Tutorial recorder demonstration"
                                        aria-disabled="true"
                                        tabindex="-1"
                                    >
                                        <span v-if="recorderMode === 'recording'" class="tutorial-recorder-re">Re</span>
                                        <AudioWaveform v-else-if="recorderMode === 'playing'" class="tutorial-recorder-icon" />
                                        <Play v-else-if="hasDemoRecording" class="tutorial-recorder-icon tutorial-recorder-icon--play fill-white" />
                                        <Mic v-else class="tutorial-recorder-icon" />
                                    </AssessmentCircleButton>
                                </div>

                                <p class="tutorial-recorder-label" aria-live="polite">
                                    {{ recorderLabel }}
                                </p>
                                <button
                                    v-if="showRetryText"
                                    type="button"
                                    class="tutorial-retry-text"
                                    data-tutorial-target="retry-text"
                                    aria-disabled="true"
                                    tabindex="-1"
                                >
                                    Retry?
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template #qa>
                    <div class="flex items-center gap-2" data-tutorial-target="manual-transcript">
                        <label class="flex min-w-0 flex-1 items-center gap-2 text-xs font-black text-slate-500">
                            <span class="shrink-0">Manual Transcript Override</span>
                            <input
                                value=""
                                class="min-h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-800 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"
                                placeholder="Optional transcript text"
                                disabled
                            >
                        </label>
                    </div>
                </template>
            </AssessmentTaskWorkspace>

            <div v-if="hasStarted && !isComplete" class="tutorial-interaction-lock" aria-hidden="true" />

            <div
                v-if="cursorVisible"
                class="tutorial-cursor"
                :class="{ 'tutorial-cursor--pressed': cursorPressed }"
                :style="{ transform: `translate3d(${cursorPosition.x}px, ${cursorPosition.y}px, 0)` }"
                aria-hidden="true"
            >
                <MousePointer2 class="tutorial-cursor-icon" />
                <span class="tutorial-cursor-label">Miss Vivian</span>
            </div>

            <audio
                v-if="demoAudioUrl"
                ref="demoAudio"
                :src="demoAudioUrl"
                class="hidden"
                preload="auto"
            />
        </div>
    </LearnerLayout>
</template>

<style scoped>
.diagnostic-tutorial-page {
    position: relative;
    height: 100%;
    min-height: 0;
}

.tutorial-item-display {
    display: grid;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    place-items: center;
}

.tutorial-recorder-shell {
    display: flex;
    min-height: 0;
    inline-size: 100%;
    block-size: 100%;
    flex: 1 1 auto;
    flex-direction: column;
    overflow: visible;
    border: 2px solid var(--rd-frame-border);
    border-radius: var(--rd-radius-frame);
    background: var(--rd-story-surface);
    padding: 10px 12px 14px;
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
    pointer-events: none;
}

.tutorial-recorder-face {
    display: flex;
    min-height: 0;
    width: 100%;
    height: 100%;
    flex: 1 1 auto;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: visible;
    border: 1.5px solid var(--rd-face-border);
    border-radius: var(--rd-radius-face);
    background: var(--rd-face-surface);
    padding: clamp(0.45rem, min(3.5cqh, 2.4cqw), 1rem);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.tutorial-recorder-group {
    display: flex;
    min-height: 0;
    max-height: 100%;
    width: 100%;
    flex: 1 1 auto;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: clamp(0.35rem, min(3.2cqh, 2cqw), 0.8rem);
    overflow: visible;
}

.tutorial-recorder-icon {
    width: var(--assessment-circle-icon-size);
    height: var(--assessment-circle-icon-size);
    stroke-width: 2.6;
}

.tutorial-recorder-icon--play {
    margin-left: calc(var(--assessment-circle-icon-size) * 0.08);
}

.tutorial-recorder-re {
    font-size: var(--assessment-circle-re-size);
    font-weight: 900;
    line-height: 1;
    letter-spacing: 0;
}

.tutorial-recorder-label,
.tutorial-retry-text {
    margin: 0;
    max-width: 100%;
    text-align: center;
    font-size: clamp(0.85rem, min(4cqh, 1.35vw), 1.125rem);
    font-weight: 900;
    line-height: 1.15;
    color: var(--rd-text-main);
    overflow-wrap: anywhere;
}

.tutorial-retry-text {
    border: 0;
    background: transparent;
    color: var(--rd-primary-orange);
    padding: 0;
    text-decoration: underline;
    text-underline-offset: 4px;
    pointer-events: none;
}

.tutorial-interaction-lock {
    position: fixed;
    z-index: 70;
    inset: 0;
    cursor: wait;
    background: transparent;
}

.tutorial-cursor {
    position: fixed;
    z-index: 90;
    top: 0;
    left: 0;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    pointer-events: none;
    transition: transform 850ms cubic-bezier(0.18, 0.9, 0.22, 1), filter 160ms ease;
    filter: drop-shadow(0 8px 12px rgba(35, 55, 70, 0.2));
}

.tutorial-cursor--pressed {
    filter: drop-shadow(0 4px 8px rgba(35, 55, 70, 0.18));
}

.tutorial-cursor-icon {
    width: 2.4rem;
    height: 2.4rem;
    transform: translate(-0.2rem, -0.15rem);
    fill: #fffdf8;
    color: #d9652f;
    stroke-width: 2.7;
}

.tutorial-cursor--pressed .tutorial-cursor-icon {
    transform: translate(-0.2rem, -0.15rem) scale(0.86);
}

.tutorial-cursor-label {
    border: 2px solid #d9652f;
    border-radius: 999px;
    background: #fffdf8;
    color: var(--rd-text-main);
    padding: 0.24rem 0.6rem;
    font-size: 0.74rem;
    font-weight: 900;
    white-space: nowrap;
    box-shadow: 0 3px 0 rgba(184, 75, 36, 0.22), 0 7px 14px rgba(54, 83, 101, 0.16);
}

.diagnostic-tutorial-page :deep(.assessment-agent-dialogue-text) {
    overflow-y: auto;
    max-height: 100%;
    padding-right: 0.15rem;
    font-size: clamp(0.74rem, 1.55dvh, 1.02rem);
    line-height: 1.18;
}

.diagnostic-tutorial-page :deep(.assessment-agent-dialogue-actions),
.diagnostic-tutorial-page :deep(.assessment-qa-row input),
.diagnostic-tutorial-page :deep(.assessment-qa-row button) {
    pointer-events: none;
}

.diagnostic-tutorial-page--complete :deep(.assessment-primary-action) {
    pointer-events: auto;
}

@media (max-width: 600px) and (orientation: portrait) {
    .tutorial-cursor-icon {
        width: 2rem;
        height: 2rem;
    }

    .tutorial-cursor-label {
        display: none;
    }

    .diagnostic-tutorial-page :deep(.assessment-agent-dialogue-text) {
        font-size: clamp(0.58rem, 2.55vw, 0.68rem);
        line-height: 1.06;
    }
}

:global(body.diagnostic-tutorial-active .rd-learner-assessment-header) {
    pointer-events: none;
}

:global(body.diagnostic-tutorial-active .assessment-agent-dialogue-actions) {
    pointer-events: none;
    opacity: 0.55;
}
</style>
