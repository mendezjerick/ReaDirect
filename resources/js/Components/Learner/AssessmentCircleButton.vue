<script setup>
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    recording: { type: Boolean, default: false },
    pulse: { type: Boolean, default: false },
    attemptSegments: { type: Array, default: () => [] },
});

const attrs = useAttrs();

const normalizedAttemptSegments = computed(() => Array.from({ length: 3 }, (_, index) => {
    const entry = props.attemptSegments[index] ?? {};
    const rawStatus = typeof entry === 'string' ? entry : entry.status;
    const status = String(rawStatus ?? 'unused').toLowerCase();

    return {
        attempt: Number(entry.attempt ?? index + 1),
        status: ['correct', 'incorrect', 'wrong'].includes(status) ? status : 'unused',
    };
}));
const hasAttemptRing = computed(() => props.attemptSegments.length > 0);
const attemptRingGradient = computed(() => {
    const colors = {
        correct: 'rgb(34 197 94)',
        incorrect: 'rgb(239 68 68)',
        wrong: 'rgb(239 68 68)',
        unused: 'rgb(203 213 225)',
    };
    const gap = 5;
    const segmentSize = 120;
    const stops = normalizedAttemptSegments.value.flatMap((segment, index) => {
        const start = (index * segmentSize) + (gap / 2);
        const end = ((index + 1) * segmentSize) - (gap / 2);
        const color = colors[segment.status] ?? colors.unused;

        return [
            `transparent ${index * segmentSize}deg ${start}deg`,
            `${color} ${start}deg ${end}deg`,
            `transparent ${end}deg ${(index + 1) * segmentSize}deg`,
        ];
    });

    return `conic-gradient(from -90deg, ${stops.join(', ')})`;
});
</script>

<template>
    <div class="assessment-circle-button-frame">
        <div
            v-if="hasAttemptRing"
            class="assessment-circle-attempt-ring"
            :style="{ background: attemptRingGradient }"
            aria-hidden="true"
        />
        <button
            v-bind="attrs"
            type="button"
            class="assessment-circle-button grid place-items-center rounded-full bg-primary text-white shadow-xl shadow-primary/25 ring-1 ring-white/40 transition hover:bg-primary-dark active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
            :class="{
                'assessment-circle-button--recording': recording,
                'assessment-circle-button--pulse': pulse,
            }"
        >
            <slot />
        </button>
    </div>
</template>

<style scoped>
.assessment-circle-button-frame {
    --assessment-circle-button-size: clamp(3rem, min(50cqh, 38cqw), 9.5rem);
    --assessment-circle-ring-gap: clamp(0.22rem, min(2.2cqh, 1.6cqw), 0.5rem);
    --assessment-circle-ring-thickness: clamp(3px, min(1.1cqh, 0.8cqw), 5px);
    --assessment-circle-icon-size: clamp(1.45rem, min(17cqh, 11cqw), 2.75rem);
    --assessment-circle-re-size: clamp(1.1rem, min(10cqh, 6cqw), 1.875rem);

    position: relative;
    display: grid;
    inline-size: calc(var(--assessment-circle-button-size) + (var(--assessment-circle-ring-gap) * 2));
    block-size: calc(var(--assessment-circle-button-size) + (var(--assessment-circle-ring-gap) * 2));
    aspect-ratio: 1 / 1;
    flex: 0 0 auto;
    place-items: center;
}

.assessment-circle-button {
    position: relative;
    isolation: isolate;
    inline-size: var(--assessment-circle-button-size);
    block-size: var(--assessment-circle-button-size);
    aspect-ratio: 1 / 1;
    min-inline-size: 0;
    min-block-size: 0;
    overflow: visible;
    will-change: transform;
}

.assessment-circle-attempt-ring {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    pointer-events: none;
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - var(--assessment-circle-ring-thickness)), #000 calc(100% - var(--assessment-circle-ring-thickness)));
    mask: radial-gradient(farthest-side, transparent calc(100% - var(--assessment-circle-ring-thickness)), #000 calc(100% - var(--assessment-circle-ring-thickness)));
}

.assessment-circle-button::before {
    content: '';
    position: absolute;
    inset: calc(var(--assessment-circle-ring-gap) * -0.8);
    z-index: -1;
    border: 2px solid rgb(59 130 246 / 0.28);
    border-radius: 9999px;
    opacity: 0;
    transform: scale(0.94);
    pointer-events: none;
}

.assessment-circle-button--recording {
    animation: hold-recording-pulse 900ms ease-in-out infinite alternate;
}

.assessment-circle-button--pulse {
    animation: hold-button-syllable-scale 640ms cubic-bezier(0.2, 0.9, 0.28, 1) infinite;
}

.assessment-circle-button--pulse::before {
    animation: hold-button-syllable-ring 640ms cubic-bezier(0.2, 0.9, 0.28, 1) infinite;
}

@keyframes hold-recording-pulse {
    from {
        box-shadow: 0 18px 32px rgb(59 130 246 / 0.24), 0 0 0 0 rgb(59 130 246 / 0.26);
    }

    to {
        box-shadow: 0 18px 32px rgb(59 130 246 / 0.18), 0 0 0 12px rgb(59 130 246 / 0);
    }
}

@keyframes hold-button-syllable-scale {
    0%,
    100% {
        transform: scale(1);
    }

    12% {
        transform: scale(1.035);
    }

    24% {
        transform: scale(0.995);
    }

    38% {
        transform: scale(1.025);
    }

    54% {
        transform: scale(1);
    }
}

@keyframes hold-button-syllable-ring {
    0% {
        opacity: 0;
        transform: scale(0.94);
    }

    12% {
        opacity: 0.38;
        transform: scale(1.02);
    }

    42% {
        opacity: 0;
        transform: scale(1.16);
    }

    58% {
        opacity: 0.22;
        transform: scale(1.04);
    }

    82%,
    100% {
        opacity: 0;
        transform: scale(1.22);
    }
}
</style>
