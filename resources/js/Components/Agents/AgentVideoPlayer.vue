<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
    getAgentActionMedia,
    getAgentActionName,
    getAgentAlt,
    getAgentFallbackMedia,
    getAgentIdleMedia,
    getAgentName,
} from '../../utils/agentMedia';

const props = defineProps({
    agent: { type: String, default: 'Ciel' },
    action: { type: String, default: 'idle' },
    alt: { type: String, default: '' },
    allowCongrats: { type: Boolean, default: false },
});

const emit = defineEmits(['interaction-ended']);

const activeAgent = ref(getAgentName(props.agent));
const activeAction = ref('idle');
const currentMedia = ref(getAgentIdleMedia(activeAgent.value));
const isBusy = ref(false);
const idleVideoFailed = ref(false);

const altText = computed(() => props.alt || getAgentAlt(activeAgent.value));
const isVideo = computed(() => currentMedia.value.type === 'video');

const showIdle = (agent = activeAgent.value) => {
    activeAgent.value = getAgentName(agent);
    activeAction.value = 'idle';
    isBusy.value = false;
    idleVideoFailed.value = false;
    currentMedia.value = getAgentIdleMedia(activeAgent.value);
};

const requestAction = (agent, action) => {
    if (isBusy.value) {
        return false;
    }

    const nextAgent = getAgentName(agent);
    const actionName = getAgentActionName(nextAgent, action, props.allowCongrats);

    activeAgent.value = nextAgent;
    idleVideoFailed.value = false;

    if (actionName === 'idle') {
        activeAction.value = 'idle';
        currentMedia.value = getAgentIdleMedia(nextAgent);
        return true;
    }

    const media = getAgentActionMedia(nextAgent, actionName, {
        allowCongrats: props.allowCongrats,
    });

    if (media.path === getAgentIdleMedia(nextAgent).path) {
        activeAction.value = 'idle';
        currentMedia.value = media;
        return true;
    }

    activeAction.value = actionName;
    isBusy.value = true;
    currentMedia.value = media;
    return true;
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

const handleMediaError = () => {
    if (isBusy.value) {
        showIdle(activeAgent.value);
        return;
    }

    if (!idleVideoFailed.value) {
        idleVideoFailed.value = true;
        currentMedia.value = getAgentFallbackMedia(activeAgent.value);
    }
};

watch(
    () => [props.agent, props.action, props.allowCongrats],
    ([agent, action]) => requestAction(agent, action),
);

onMounted(() => requestAction(props.agent, props.action));

defineExpose({
    isBusy,
    requestAction,
});
</script>

<template>
    <video
        v-if="isVideo"
        :key="currentMedia.url"
        :src="currentMedia.url"
        :aria-label="altText"
        :loop="!isBusy"
        autoplay
        muted
        playsinline
        @ended="handleVideoEnded"
        @error="handleMediaError"
    />
    <img
        v-else
        :src="currentMedia.url"
        :alt="altText"
    >
</template>
