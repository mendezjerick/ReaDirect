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
    <section class="grid gap-3 rounded-[28px] border border-border bg-surface shadow-xl shadow-primary/10 md:items-center" :class="compact ? 'p-3 md:grid-cols-[112px_1fr]' : 'p-4 md:grid-cols-[220px_1fr]'">
        <div class="grid justify-items-center">
            <div class="grid place-items-end overflow-hidden rounded-[24px] bg-primary-light" :class="compact ? 'h-28 w-24 md:h-32 md:w-28' : 'h-64 w-52'">
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
        <div class="relative rounded-[24px] border-2 border-primary-light bg-background shadow-sm" :class="compact ? 'p-4' : 'p-5'">
            <span class="absolute left-1/2 top-0 size-4 -translate-x-1/2 -translate-y-1/2 rotate-45 border-l-2 border-t-2 border-primary-light bg-background md:left-0 md:top-1/2 md:-translate-x-1/2 md:-translate-y-1/2" aria-hidden="true" />
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-black uppercase text-primary" :class="compact ? 'text-xs' : 'text-sm'">{{ displayTitle }}</p>
                    <p v-if="subtitle" class="mt-1 text-sm font-bold text-muted">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-primary-light px-2.5 py-1 text-[11px] font-black text-primary">{{ stateLabel }}</span>
                    <button v-if="showAudioButton" type="button" disabled class="grid size-9 place-items-center rounded-full bg-border text-muted" aria-label="Audio coming soon">
                        <Volume2 class="size-4" />
                    </button>
                </div>
            </div>
            <p class="font-black leading-relaxed text-text" :class="compact ? 'mt-2 text-base md:text-lg' : 'mt-4 text-xl'">
                {{ message }}
            </p>
        </div>
    </section>
</template>
