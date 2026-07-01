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
const attemptRingSegments = computed(() => {
    const paths = [
        'M 10.2 49 A 40.5 40.5 0 0 1 23.4 20.3',
        'M 30.2 14.6 A 40.5 40.5 0 0 1 69.8 14.6',
        'M 76.6 20.3 A 40.5 40.5 0 0 1 89.8 49',
    ];

    return normalizedAttemptSegments.value.map((segment, index) => {
        const status = segment.status === 'wrong' ? 'incorrect' : segment.status;

        return {
            ...segment,
            path: paths[index],
            statusClass: `assessment-circle-attempt-segment--${status}`,
        };
    });
});
</script>

<template>
    <div class="assessment-circle-button-frame">
        <svg
            v-if="hasAttemptRing"
            class="assessment-circle-attempt-ring"
            viewBox="0 0 100 100"
            focusable="false"
            aria-hidden="true"
        >
            <path
                v-for="segment in attemptRingSegments"
                :key="segment.attempt"
                class="assessment-circle-attempt-segment"
                :class="segment.statusClass"
                :d="segment.path"
            />
        </svg>
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
    --assessment-circle-ring-offset: clamp(1.75rem, min(13cqh, 9.5cqw), 2.75rem);
    --assessment-circle-icon-size: clamp(1.55rem, min(18cqh, 12cqw), 3rem);
    --assessment-circle-re-size: clamp(1.1rem, min(10cqh, 6cqw), 1.875rem);

    position: relative;
    display: grid;
    inline-size: calc(var(--assessment-circle-button-size) + (var(--assessment-circle-ring-offset) * 2));
    block-size: calc(var(--assessment-circle-button-size) + (var(--assessment-circle-ring-offset) * 2));
    aspect-ratio: 1 / 1;
    flex: 0 0 auto;
    place-items: center;
}

.assessment-circle-button {
    position: relative;
    z-index: 1;
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
    z-index: 0;
    overflow: visible;
    pointer-events: none;
}

.assessment-circle-attempt-segment {
    fill: none;
    stroke: rgba(224, 207, 166, 0.58);
    stroke-width: 4.4;
    stroke-linecap: round;
}

.assessment-circle-attempt-segment--correct {
    stroke: var(--rd-correct-green, #585123);
}

.assessment-circle-attempt-segment--incorrect {
    stroke: var(--rd-wrong-red, #772F1A);
}

.assessment-circle-attempt-segment--unused {
    stroke: rgba(224, 207, 166, 0.58);
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
        --assessment-circle-ring-offset: clamp(1.45rem, 8.5vw, 2.1rem);
        --assessment-circle-icon-size: clamp(2.25rem, 9vw, 3rem);
        --assessment-circle-re-size: clamp(1.45rem, 6vw, 2rem);
    }
}

:global(body[data-qa-viewport='mobile-vertical'] .assessment-circle-button-frame) {
    --assessment-circle-button-size: 5.85rem;
    --assessment-circle-ring-offset: 1.85rem;
    --assessment-circle-icon-size: 2.35rem;
    --assessment-circle-re-size: 1.62rem;
}

</style>
