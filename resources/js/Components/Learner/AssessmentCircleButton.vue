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
        correct: '#585123',
        incorrect: '#772F1A',
        wrong: '#772F1A',
        unused: 'transparent',
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
            class="assessment-circle-button grid place-items-center rounded-full transition disabled:cursor-not-allowed"
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
    --assessment-circle-ring-gap: 0px;
    --assessment-circle-ring-thickness: clamp(3px, min(1.1cqh, 0.8cqw), 5px);
    --assessment-circle-icon-size: clamp(1.55rem, min(18cqh, 12cqw), 3rem);
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
    border: 0;
    outline: 0;
    appearance: none;
    -webkit-appearance: none;
    background: #FF844C;
    background-clip: border-box;
    color: #ffffff;
    box-shadow:
        0 clamp(0.46rem, min(4.4cqh, 2.7cqw), 0.8rem) 0 #B84B24,
        0 clamp(0.9rem, min(7.4cqh, 4.6cqw), 1.35rem) clamp(0.95rem, min(8cqh, 5cqw), 1.55rem) rgba(54, 83, 101, 0.28);
    will-change: transform;
}

.assessment-circle-button:focus,
.assessment-circle-button:focus-visible {
    outline: 0;
}

.assessment-circle-button:hover:not(:disabled) {
    background: #FF8A4C;
}

.assessment-circle-button:active:not(:disabled) {
    transform: translateY(clamp(0.25rem, min(2.8cqh, 1.8cqw), 0.5rem));
    box-shadow:
        0 clamp(0.14rem, min(1.4cqh, 0.9cqw), 0.26rem) 0 #B84B24,
        0 clamp(0.42rem, min(4cqh, 2.5cqw), 0.75rem) clamp(0.55rem, min(5cqh, 3cqw), 0.95rem) rgba(54, 83, 101, 0.22);
}

.assessment-circle-button:disabled {
    opacity: 0.65;
    filter: saturate(0.78);
}

.assessment-circle-attempt-ring {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    pointer-events: none;
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - var(--assessment-circle-ring-thickness)), #000 calc(100% - var(--assessment-circle-ring-thickness)));
    mask: radial-gradient(farthest-side, transparent calc(100% - var(--assessment-circle-ring-thickness)), #000 calc(100% - var(--assessment-circle-ring-thickness)));
}

.assessment-circle-button--recording {
    animation: hold-recording-pulse 900ms ease-in-out infinite alternate;
}

.assessment-circle-button--pulse {
    animation: hold-button-syllable-scale 640ms cubic-bezier(0.2, 0.9, 0.28, 1) infinite;
}

@keyframes hold-recording-pulse {
    from {
        box-shadow:
            0 clamp(0.46rem, min(4.4cqh, 2.7cqw), 0.8rem) 0 #B84B24,
            0 clamp(0.9rem, min(7.4cqh, 4.6cqw), 1.35rem) clamp(0.95rem, min(8cqh, 5cqw), 1.55rem) rgba(54, 83, 101, 0.26);
    }

    to {
        box-shadow:
            0 clamp(0.46rem, min(4.4cqh, 2.7cqw), 0.8rem) 0 #B84B24,
            0 clamp(0.9rem, min(7.4cqh, 4.6cqw), 1.35rem) clamp(0.95rem, min(8cqh, 5cqw), 1.55rem) rgba(54, 83, 101, 0.22);
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

@media (max-width: 600px) and (orientation: portrait) {
    .assessment-circle-button-frame {
        --assessment-circle-button-size: clamp(5.25rem, 24vw, 6.75rem);
        --assessment-circle-ring-thickness: clamp(3px, 1.2vw, 5px);
        --assessment-circle-icon-size: clamp(2.25rem, 9vw, 3rem);
        --assessment-circle-re-size: clamp(1.45rem, 6vw, 2rem);
    }
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-circle-button-frame) {
    --assessment-circle-button-size: 5.85rem;
    --assessment-circle-ring-thickness: 4px;
    --assessment-circle-icon-size: 2.35rem;
    --assessment-circle-re-size: 1.62rem;
}

</style>
