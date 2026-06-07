<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    getAgentActionMedia,
    getAgentAlt,
    getAgentFallbackMedia,
    getAgentIdleMedia,
} from '../../utils/agentMedia';
import { resolveAgentInteraction } from '../../utils/agentInteraction';

const props = defineProps({
    agent: { type: String, default: 'Ciel' },
    agentType: { type: String, default: '' },
    action: { type: String, default: 'idle' },
    context: { type: String, default: '' },
    route: { type: String, default: '' },
    alt: { type: String, default: '' },
    allowCongrats: { type: Boolean, default: false },
});

const emit = defineEmits(['interaction-ended']);

const initialCue = resolveAgentInteraction({
    agent: props.agent,
    agentType: props.agentType,
    action: 'idle',
    context: props.context,
    route: props.route,
});
const activeAgent = ref(initialCue.agent);
const activeAction = ref('idle');
const idleMedia = ref(getAgentIdleMedia(activeAgent.value));
const idleFallback = ref(false);
const interactionMedia = ref(null);
const interactionReady = ref(false);
const interactionVideo = ref(null);
const isBusy = ref(false);
let readyTimer = null;

const altText = computed(() => props.alt || getAgentAlt(activeAgent.value));
const visibleIdleMedia = computed(() => idleFallback.value
    ? getAgentFallbackMedia(activeAgent.value)
    : idleMedia.value);

const clearReadyTimer = () => {
    if (readyTimer !== null) {
        window.clearTimeout(readyTimer);
        readyTimer = null;
    }
};

const resetInteraction = () => {
    clearReadyTimer();
    interactionMedia.value = null;
    interactionReady.value = false;
    interactionVideo.value = null;
    activeAction.value = 'idle';
    isBusy.value = false;
};

const showIdle = (agent = activeAgent.value) => {
    activeAgent.value = agent;
    idleMedia.value = getAgentIdleMedia(agent);
    idleFallback.value = false;
    resetInteraction();
};

const requestAction = (agent, action, options = {}) => {
    if (isBusy.value) return false;

    const cue = resolveAgentInteraction({
        agent,
        agentType: options.agentType ?? props.agentType,
        action,
        context: options.context ?? props.context,
        route: options.route ?? props.route,
        congratsAllowed: options.allowCongrats ?? props.allowCongrats,
    });

    if (cue.agent !== activeAgent.value) {
        showIdle(cue.agent);
    }

    if (!cue.shouldInteract) {
        showIdle(cue.agent);
        return true;
    }

    const media = getAgentActionMedia(cue.agent, cue.action, {
        allowCongrats: cue.congratsAllowed,
    });

    if (media.type !== 'video' || media.path === getAgentIdleMedia(cue.agent).path) {
        showIdle(cue.agent);
        return true;
    }

    activeAgent.value = cue.agent;
    activeAction.value = cue.action;
    interactionMedia.value = media;
    interactionReady.value = false;
    isBusy.value = true;

    clearReadyTimer();
    readyTimer = window.setTimeout(() => {
        if (isBusy.value && !interactionReady.value) {
            resetInteraction();
        }
    }, 5000);

    return true;
};

const handleInteractionReady = async () => {
    if (!isBusy.value || !interactionMedia.value || interactionReady.value) return;

    clearReadyTimer();
    interactionReady.value = true;

    try {
        await interactionVideo.value?.play();
    } catch {
        resetInteraction();
    }
};

const handleVideoEnded = () => {
    if (!isBusy.value) return;

    const completed = {
        agent: activeAgent.value,
        action: activeAction.value,
    };
    resetInteraction();
    emit('interaction-ended', completed);
};

const handleInteractionError = () => {
    resetInteraction();
};

const handleIdleError = () => {
    idleFallback.value = true;
};

watch(
    () => [
        props.agent,
        props.agentType,
        props.action,
        props.context,
        props.route,
        props.allowCongrats,
    ],
    ([agent, agentType, action, context, route, allowCongrats]) => {
        requestAction(agent, action, {
            agentType,
            context,
            route,
            allowCongrats,
        });
    },
);

onMounted(() => requestAction(props.agent, props.action));
onBeforeUnmount(clearReadyTimer);

defineExpose({
    isBusy,
    requestAction,
});
</script>

<template>
    <span class="agent-media-player">
        <video
            v-if="visibleIdleMedia.type === 'video'"
            :key="visibleIdleMedia.url"
            class="agent-media-layer"
            :src="visibleIdleMedia.url"
            :aria-label="altText"
            preload="auto"
            autoplay
            loop
            muted
            playsinline
            @error="handleIdleError"
        />
        <img
            v-else
            class="agent-media-layer"
            :src="visibleIdleMedia.url"
            :alt="altText"
        >
        <video
            v-if="interactionMedia"
            ref="interactionVideo"
            class="agent-media-layer agent-media-interaction"
            :class="{ 'agent-media-interaction--ready': interactionReady }"
            :src="interactionMedia.url"
            :aria-label="altText"
            preload="auto"
            muted
            playsinline
            @loadeddata="handleInteractionReady"
            @canplay="handleInteractionReady"
            @ended="handleVideoEnded"
            @error="handleInteractionError"
        />
    </span>
</template>

<style scoped>
.agent-media-player {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
}

.agent-media-layer {
    position: absolute;
    inset: 0;
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center bottom;
    image-rendering: auto;
}

.agent-media-interaction {
    z-index: 1;
    visibility: hidden;
}

.agent-media-interaction--ready {
    visibility: visible;
}
</style>
