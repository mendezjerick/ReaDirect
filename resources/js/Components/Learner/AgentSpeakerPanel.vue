<script setup>
import { computed, ref, watch } from 'vue';
import { Volume2 } from 'lucide-vue-next';

const props = defineProps({
    agentType: { type: String, required: true },
    state: { type: String, default: 'idle' },
    message: { type: String, required: true },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    compact: Boolean,
    showAudioButton: Boolean,
});

const agents = {
    assessment: { label: 'Assessment Agent', initials: 'AA', base: '/assets/agents/assessment' },
    coach_feedback: { label: 'Coach + Feedback Agent', initials: 'CF', base: '/assets/agents/coach_feedback' },
    evaluator: { label: 'Evaluator / Recommendation Agent', initials: 'ER', base: '/assets/agents/evaluator' },
};

const displayMode = ref('requested');

const agent = computed(() => agents[props.agentType] ?? agents.assessment);
const requestedSrc = computed(() => `${agent.value.base}/${props.state || 'idle'}.png`);
const idleSrc = computed(() => `${agent.value.base}/idle.png`);
const imageSrc = computed(() => (displayMode.value === 'requested' ? requestedSrc.value : idleSrc.value));
const showPlaceholder = computed(() => displayMode.value === 'placeholder');
const displayTitle = computed(() => props.title || agent.value.label);
const stateLabel = computed(() => {
    const labels = {
        idle: 'Ready',
        speaking: 'Speaking',
        listening: 'Listening',
        thinking: 'Thinking',
        encouraging: 'Encouraging',
        happy: 'Happy',
        celebrating: 'Celebrating',
        confused: 'Thinking',
        pointing: 'Pointing',
    };

    return labels[props.state] ?? 'Ready';
});
const animationClass = computed(() => `agent-animate-${props.state || 'idle'}`);

watch(() => [props.agentType, props.state], () => {
    displayMode.value = 'requested';
});

const handleImageError = () => {
    if (displayMode.value === 'requested' && props.state !== 'idle') {
        displayMode.value = 'idle';
        return;
    }

    displayMode.value = 'placeholder';
};
</script>

<template>
    <section class="agent-speaker-panel grid gap-3 rounded-[24px] border border-border bg-surface shadow-xl shadow-primary/10 md:items-center" :class="compact ? 'p-2.5 md:grid-cols-[86px_1fr] lg:grid-cols-1' : 'p-3 md:grid-cols-[132px_1fr] lg:grid-cols-1'">
        <div class="grid justify-items-center">
            <div class="grid place-items-end overflow-hidden rounded-[20px] bg-primary-light" :class="compact ? 'h-24 w-20 md:h-24 md:w-20 lg:h-36 lg:w-32' : 'h-36 w-32 md:h-40 md:w-36 lg:h-52 lg:w-44'">
                <img
                    v-if="!showPlaceholder"
                    :src="imageSrc"
                    :alt="displayTitle"
                    class="h-full w-full object-contain"
                    :class="animationClass"
                    @error="handleImageError"
                >
                <div v-else class="grid size-full place-items-center bg-primary font-black text-white" :class="[animationClass, compact ? 'text-2xl' : 'text-4xl']">
                    {{ agent.initials }}
                </div>
            </div>
        </div>
        <div class="relative rounded-[22px] border-2 border-primary-light bg-background shadow-sm" :class="compact ? 'p-3 lg:p-4' : 'p-4'">
            <span class="absolute left-1/2 top-0 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l-2 border-t-2 border-primary-light bg-background md:left-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2 lg:left-1/2 lg:top-0 lg:-translate-x-1/2 lg:-translate-y-1/2" aria-hidden="true" />
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-black uppercase text-primary" :class="compact ? 'text-xs' : 'text-sm'">{{ displayTitle }}</p>
                    <p v-if="subtitle" class="mt-1 text-sm font-bold text-muted">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-primary-light px-2 py-0.5 text-[10px] font-black text-primary">{{ stateLabel }}</span>
                    <button v-if="showAudioButton" type="button" disabled class="grid size-9 place-items-center rounded-full bg-border text-muted" aria-label="Audio coming soon">
                        <Volume2 class="size-4" />
                    </button>
                </div>
            </div>
            <p class="font-black leading-snug text-text" :class="compact ? 'mt-2 text-sm md:text-base lg:text-[17px]' : 'mt-3 text-lg'">
                {{ message }}
            </p>
        </div>
    </section>
</template>
