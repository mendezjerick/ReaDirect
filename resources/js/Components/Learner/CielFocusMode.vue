<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Star } from 'lucide-vue-next';
import AgentSpeakerTTS from '../Agents/AgentSpeakerTTS.vue';
import AgentVideoPlayer from '../Agents/AgentVideoPlayer.vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    mode: { type: String, default: 'teaching' },
    targetType: { type: String, default: null },
    targetText: { type: String, default: null },
    dialogueSteps: { type: Array, default: () => [] },
    reward: { type: Object, default: null },
});

const emit = defineEmits(['closed']);

const currentIndex = ref(0);
const audioPayload = ref(null);
const voiceLoading = ref(false);
const voiceRequestId = ref(0);
const voiceError = ref('');
const focusComplete = ref(false);
let stepTimer = null;

const steps = computed(() => props.dialogueSteps?.length
    ? props.dialogueSteps
    : [{ text: 'Let us practice together.', action: 'talk' }]);
const currentStep = computed(() => steps.value[Math.min(currentIndex.value, steps.value.length - 1)] ?? steps.value[0]);
const mediaAction = computed(() => focusComplete.value ? 'c-idle' : (currentStep.value?.action ?? 'c-talk'));
const targetLabel = computed(() => props.targetText ? String(props.targetText) : '');
const progressLabel = computed(() => `${currentIndex.value + 1} / ${steps.value.length}`);
const naturalAudioUrl = computed(() => audioPayload.value?.audio_url ?? null);
const isReward = computed(() => props.mode === 'reward' && props.reward);

const clearStepTimer = () => {
    if (stepTimer !== null) {
        window.clearTimeout(stepTimer);
        stepTimer = null;
    }
};

const close = () => {
    clearStepTimer();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
    emit('closed');
};

const finishDialogue = () => {
    clearStepTimer();
    focusComplete.value = true;
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
};

const readableDelay = (text) => {
    const length = String(text ?? '').length;

    return Math.min(5600, Math.max(2400, 1400 + length * 45));
};

const scheduleNext = (delay = null) => {
    clearStepTimer();

    if (!props.visible || typeof window === 'undefined') {
        return;
    }

    stepTimer = window.setTimeout(() => {
        if (currentIndex.value >= steps.value.length - 1) {
            finishDialogue();
            return;
        }

        currentIndex.value += 1;
    }, delay ?? readableDelay(currentStep.value?.text));
};

const continueStep = () => {
    if (focusComplete.value) {
        close();
        return;
    }

    if (currentIndex.value >= steps.value.length - 1) {
        finishDialogue();
        return;
    }

    currentIndex.value += 1;
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const loadNaturalVoice = async () => {
    const text = String(currentStep.value?.text ?? '').trim();
    const requestId = voiceRequestId.value + 1;
    voiceRequestId.value = requestId;
    audioPayload.value = null;
    voiceError.value = '';

    if (!props.visible || !text || typeof window === 'undefined') {
        return;
    }

    voiceLoading.value = true;

    try {
        const response = await fetch('/agent-voice/synthesize', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                agent: 'coach_feedback',
                text,
            }),
        });

        if (requestId !== voiceRequestId.value) {
            return;
        }

        audioPayload.value = response.ok ? await response.json() : { audio_url: null };
    } catch {
        if (requestId === voiceRequestId.value) {
            audioPayload.value = { audio_url: null };
        }
    } finally {
        if (requestId === voiceRequestId.value) {
            voiceLoading.value = false;
        }
    }
};

watch(
    () => props.visible,
    (visible) => {
        clearStepTimer();

        if (!visible) {
            currentIndex.value = 0;
            focusComplete.value = false;
            return;
        }

        currentIndex.value = 0;
        focusComplete.value = false;
        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
        }
        loadNaturalVoice();
    },
);

watch(
    () => currentStep.value?.text,
    () => {
        if (!props.visible || focusComplete.value) {
            return;
        }

        if (typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
        }
        loadNaturalVoice();
    },
);

const handleSpeakingStart = () => {
    voiceError.value = '';
    clearStepTimer();
};

const handleSpeakingEnd = () => {
    if (focusComplete.value) return;

    scheduleNext(750);
};

const handleVoiceError = () => {
    if (focusComplete.value) return;

    voiceError.value = 'Voice is unavailable. Read the message here.';
    scheduleNext();
};

onBeforeUnmount(() => {
    clearStepTimer();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
    }
});
</script>

<template>
    <Transition name="ciel-focus">
        <section
            v-if="visible"
            class="ciel-focus"
            role="dialog"
            aria-modal="true"
            aria-live="polite"
            aria-label="Ciel Focus Mode"
            @pointerdown="close"
        >
            <AgentSpeakerTTS
                v-if="!voiceLoading && !focusComplete"
                :key="`${currentIndex}-${naturalAudioUrl ?? 'text'}`"
                agent-type="coach_feedback"
                :message="currentStep.text"
                :audio-url="naturalAudioUrl"
                :mute="false"
                @speaking-start="handleSpeakingStart"
                @speaking-end="handleSpeakingEnd"
                @error="handleVoiceError"
            />

            <div class="ciel-focus__content">
                <div class="ciel-focus__target-wrap">
                    <p v-if="targetType" class="ciel-focus__target-label">
                        Practice this {{ targetType }}
                    </p>
                    <div v-if="targetLabel" class="ciel-focus__target">
                        {{ targetLabel }}
                    </div>
                    <div v-else-if="isReward" class="ciel-focus__reward">
                        <Star class="ciel-focus__reward-icon" />
                        +{{ reward.amount ?? 1 }} Star
                    </div>
                </div>

                <div class="ciel-focus__bottom">
                    <div class="ciel-focus__ciel" aria-hidden="true">
                        <AgentVideoPlayer
                            agent="ciel"
                            agent-type="coach_feedback"
                            context="module_focus"
                            :action="mediaAction"
                            loop-interaction
                        />
                    </div>

                    <div class="ciel-focus__dialogue">
                        <div class="ciel-focus__rule" />
                        <p class="ciel-focus__text">{{ currentStep.text }}</p>
                        <p v-if="voiceError" class="ciel-focus__voice-error">{{ voiceError }}</p>
                        <button
                            type="button"
                            class="ciel-focus__continue"
                            :aria-label="focusComplete ? 'Exit Ciel Focus Mode' : 'Continue Ciel dialogue'"
                            @click="continueStep"
                        >
                            ▶
                        </button>
                        <span class="ciel-focus__progress">{{ progressLabel }}</span>
                    </div>
                </div>
            </div>
        </section>
    </Transition>
</template>

<style scoped>
.ciel-focus {
    position: fixed;
    inset: 0;
    z-index: 80;
    overflow: hidden;
    color: #1f2937;
    background: #ffffff;
}

.ciel-focus__content {
    position: relative;
    display: grid;
    min-height: 100svh;
    grid-template-rows: 1fr auto;
    overflow: hidden;
    background: #ffffff;
}

.ciel-focus__target-wrap {
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 0.8rem;
    position: relative;
    z-index: 4;
    min-height: calc(100svh - var(--ciel-dialogue-height));
    padding: clamp(1.5rem, 4vw, 3rem);
    padding-bottom: 14vh;
}

.ciel-focus__target-label {
    margin: 0;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.06);
    padding: 0.6rem 1.25rem;
    color: #334155;
    font-size: clamp(0.9rem, 1.6vw, 1.2rem);
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.ciel-focus__target {
    color: #111827;
    font-size: clamp(6.5rem, 20vw, 17rem);
    font-weight: 1000;
    line-height: 0.9;
    text-align: center;
    letter-spacing: -0.08em;
}

.ciel-focus__reward {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.06);
    padding: 1rem 1.5rem;
    color: #111827;
    font-size: clamp(2.2rem, 7vw, 5rem);
    font-weight: 1000;
}

.ciel-focus__reward-icon {
    height: 1em;
    width: 1em;
    fill: #facc15;
    color: #facc15;
}

.ciel-focus__bottom {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    height: var(--ciel-dialogue-height);
    pointer-events: none;
}

.ciel-focus__ciel {
    position: fixed;
    left: clamp(-28px, 2vw, 42px);
    bottom: -0.8rem;
    z-index: 2;
    width: clamp(330px, 38vw, 560px);
    height: clamp(440px, 58vh, 620px);
    pointer-events: none;
}

.ciel-focus__dialogue {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 3;
    height: var(--ciel-dialogue-height);
    min-height: 72px;
    max-height: 98px;
    border-top: 0;
    background: linear-gradient(
        to right,
        rgba(10, 12, 18, 0.24) 0%,
        rgba(10, 12, 18, 0.44) 18%,
        rgba(10, 12, 18, 0.78) 38%,
        rgba(10, 12, 18, 0.90) 100%
    );
    backdrop-filter: blur(2px);
    color: #ffffff;
    display: flex;
    align-items: center;
    padding: 0.45rem clamp(1.2rem, 4vw, 3rem);
    padding-left: clamp(390px, 29vw, 560px);
    pointer-events: auto;
    box-shadow: none;
}

.ciel-focus__rule {
    display: none;
}

.ciel-focus__progress {
    position: absolute;
    right: clamp(1rem, 3vw, 2rem);
    bottom: 0.75rem;
    color: rgba(255, 255, 255, 0.56);
    font-size: 0.8rem;
    font-weight: 800;
}

.ciel-focus__text {
    margin: 0;
    max-width: 58rem;
    font-size: clamp(1.05rem, 1.45vw, 1.35rem);
    font-weight: 800;
    line-height: 1.35;
    text-align: left;
}

.ciel-focus__voice-error {
    margin: 0.45rem 0 0;
    color: rgba(255, 255, 255, 0.66);
    font-size: 0.82rem;
    font-weight: 700;
}

.ciel-focus {
    --ciel-dialogue-height: clamp(72px, 9vh, 98px);
}

.ciel-focus__continue {
    position: absolute;
    right: clamp(1.2rem, 3vw, 2rem);
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #facc15;
    font-size: clamp(1rem, 1.8vw, 1.35rem);
    font-weight: 1000;
    cursor: pointer;
    text-shadow: 0 0 10px rgba(250, 204, 21, 0.35);
}

.ciel-focus-enter-active,
.ciel-focus-leave-active {
    transition: opacity 520ms ease;
}

.ciel-focus-enter-from,
.ciel-focus-leave-to {
    opacity: 0;
}

.ciel-focus-enter-active .ciel-focus__target-wrap,
.ciel-focus-enter-active .ciel-focus__dialogue,
.ciel-focus-enter-active .ciel-focus__ciel {
    transition: transform 560ms cubic-bezier(0.22, 1, 0.36, 1), opacity 560ms ease;
}

.ciel-focus-enter-from .ciel-focus__target-wrap {
    opacity: 0;
    transform: scale(0.94);
}

.ciel-focus-enter-from .ciel-focus__dialogue {
    opacity: 0;
    transform: translateY(3rem);
}

.ciel-focus-enter-from .ciel-focus__ciel {
    opacity: 0;
    transform: translate(-2rem, 4rem) scale(0.96);
}

@media (max-width: 640px) {
    .ciel-focus {
        --ciel-dialogue-height: clamp(76px, 10vh, 96px);
    }

    .ciel-focus__target-wrap {
        min-height: calc(100svh - var(--ciel-dialogue-height));
        padding-bottom: 12vh;
    }

    .ciel-focus__ciel {
        left: -4.2rem;
        bottom: -0.6rem;
        width: clamp(260px, 76vw, 330px);
        height: clamp(315px, 52vh, 410px);
    }

    .ciel-focus__dialogue {
        height: var(--ciel-dialogue-height);
        min-height: 76px;
        padding-left: clamp(9.2rem, 44vw, 13rem);
        padding-right: 2.4rem;
    }

    .ciel-focus__text {
        margin-top: 0;
        font-size: clamp(0.95rem, 3.6vw, 1.08rem);
        line-height: 1.28;
    }

    .ciel-focus__progress {
        bottom: 0.45rem;
    }
}

@media (max-height: 620px) {
    .ciel-focus {
        --ciel-dialogue-height: clamp(68px, 10vh, 88px);
    }

    .ciel-focus__target-wrap {
        padding-bottom: 8vh;
    }

    .ciel-focus__ciel {
        height: clamp(300px, 58vh, 390px);
    }

    .ciel-focus__dialogue {
        min-height: 68px;
        padding-top: 0.42rem;
        padding-bottom: 0.45rem;
    }
}
</style>
