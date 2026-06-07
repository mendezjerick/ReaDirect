<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    getAgentActionMedia,
    getAgentActionName,
    getAgentAlt,
    getAgentIdleMedia,
    getAgentName,
} from '../../utils/agentMedia';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    agent: { type: String, default: 'Ciel' },
    action: { type: String, default: 'idle' },
    alt: { type: String, default: '' },
    allowCongrats: { type: Boolean, default: false },
});

const emit = defineEmits(['interaction-ended']);

const activeAgent = ref(getAgentName(props.agent));
const activeAction = ref('idle');
const idleMedia = ref(getAgentIdleMedia(activeAgent.value));
const interactionMedia = ref(null);
const isBusy = ref(false);
const videoReady = ref(false);
const videoElement = ref(null);
const idleImageFallbackApplied = ref(false);
let videoReadyTimeoutId = null;

const altText = computed(() => props.alt || getAgentAlt(activeAgent.value));

const clearVideoReadyTimeout = () => {
    if (videoReadyTimeoutId !== null) {
        window.clearTimeout(videoReadyTimeoutId);
        videoReadyTimeoutId = null;
    }
};

const logVideoFailure = (message) => {
    if (import.meta.env?.DEV) {
        console.warn(message);
    }
};

const showIdle = (agent = activeAgent.value) => {
    clearVideoReadyTimeout();
    activeAgent.value = getAgentName(agent);
    activeAction.value = 'idle';
    idleMedia.value = getAgentIdleMedia(activeAgent.value);
    idleImageFallbackApplied.value = false;
    interactionMedia.value = null;
    videoReady.value = false;
    isBusy.value = false;
};

const requestAction = (agent, action) => {
    if (isBusy.value) {
        return false;
    }

    const nextAgent = getAgentName(agent);
    const actionName = getAgentActionName(nextAgent, action, props.allowCongrats);

    activeAgent.value = nextAgent;
    idleMedia.value = getAgentIdleMedia(nextAgent);
    idleImageFallbackApplied.value = false;

    if (actionName === 'idle') {
        showIdle(nextAgent);
        return true;
    }

    const media = getAgentActionMedia(nextAgent, actionName, {
        allowCongrats: props.allowCongrats,
    });

    if (media.type !== 'video') {
        showIdle(nextAgent);
        return true;
    }

    activeAction.value = actionName;
    isBusy.value = true;
    videoReady.value = false;
    interactionMedia.value = media;
    clearVideoReadyTimeout();
    videoReadyTimeoutId = window.setTimeout(() => {
        logVideoFailure(`Agent interaction video was not ready in time: ${media.url}`);
        showIdle(nextAgent);
    }, 5_000);

    return true;
};

const handleVideoReady = async () => {
    if (!isBusy.value || videoReady.value || !interactionMedia.value) {
        return;
    }

    clearVideoReadyTimeout();
    videoReady.value = true;
    await nextTick();

    if (!videoElement.value || !isBusy.value) {
        return;
    }

    try {
        videoElement.value.currentTime = 0;
        await videoElement.value.play();
    } catch {
        logVideoFailure(`Agent interaction video could not start: ${interactionMedia.value.url}`);
        showIdle(activeAgent.value);
    }
};

const handleVideoEnded = () => {
    if (isBusy.value) {
        const completed = {
            agent: activeAgent.value,
            action: activeAction.value,
        };
        showIdle(activeAgent.value);
        emit('interaction-ended', completed);
    }
};

const handleVideoError = () => {
    const failedUrl = interactionMedia.value?.url;
    logVideoFailure(`Agent interaction video failed to load: ${failedUrl ?? 'unknown video'}`);
    showIdle(activeAgent.value);
};

const handleIdleImageError = () => {
    if (!idleImageFallbackApplied.value && activeAgent.value !== 'Ciel') {
        idleImageFallbackApplied.value = true;
        idleMedia.value = getAgentIdleMedia('Ciel');
    }
};

watch(
    () => [props.agent, props.action, props.allowCongrats],
    ([agent, action]) => requestAction(agent, action),
);

onMounted(() => requestAction(props.agent, props.action));
onBeforeUnmount(clearVideoReadyTimeout);

defineExpose({
    isBusy,
    requestAction,
});
</script>

<template>
    <div
        v-bind="$attrs"
        class="relative overflow-hidden"
    >
        <img
            :src="idleMedia.url"
            :alt="altText"
            :aria-hidden="videoReady"
            class="agent-media__idle size-full"
            @error="handleIdleImageError"
        >
        <video
            v-if="interactionMedia"
            ref="videoElement"
            :key="interactionMedia.url"
            :src="interactionMedia.url"
            :aria-label="altText"
            :class="[
                'agent-media__video absolute inset-0 size-full transition-opacity duration-100',
                videoReady ? 'opacity-100' : 'pointer-events-none opacity-0',
            ]"
            preload="auto"
            autoplay
            muted
            playsinline
            @loadeddata="handleVideoReady"
            @canplay="handleVideoReady"
            @canplaythrough="handleVideoReady"
            @ended="handleVideoEnded"
            @error="handleVideoError"
        />
    </div>
</template>

<style scoped>
.agent-media__idle,
.agent-media__video {
    object-fit: inherit;
    object-position: inherit;
}
</style>
