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
<<<<<<< HEAD
            class="assessment-circle-button grid place-items-center rounded-full text-[#1E3A8A] transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
=======
            class="assessment-circle-button grid place-items-center rounded-full transition disabled:cursor-not-allowed"
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
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
<<<<<<< HEAD
    --assessment-circle-button-size: clamp(7rem, min(75cqh, 65cqw), 13.5rem);
    --assessment-circle-ring-gap: clamp(0.25rem, min(2.5cqh, 1.8cqw), 0.6rem);
    --assessment-circle-ring-thickness: clamp(3px, min(1.1cqh, 0.8cqw), 5px);
    --assessment-circle-icon-size: clamp(1.4rem, min(18cqh, 12cqw), 3.5rem);
    --assessment-circle-re-size: clamp(1.25rem, min(12cqh, 8cqw), 2rem);
=======
    --assessment-circle-button-size: clamp(3rem, min(50cqh, 38cqw), 9.5rem);
    --assessment-circle-ring-gap: 0px;
    --assessment-circle-ring-thickness: clamp(3px, min(1.1cqh, 0.8cqw), 5px);
    --assessment-circle-icon-size: clamp(1.55rem, min(18cqh, 12cqw), 3rem);
    --assessment-circle-re-size: clamp(1.1rem, min(10cqh, 6cqw), 1.875rem);
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0

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
    max-inline-size: var(--assessment-circle-button-size);
    max-block-size: var(--assessment-circle-button-size);
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
    background: #DBEAFE;
    border: 4px solid #3B82F6;
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
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

<<<<<<< HEAD
.assessment-circle-button::before {
    content: '';
    position: absolute;
    inset: calc(var(--assessment-circle-ring-gap) * -0.8);
    z-index: -1;
    border-radius: 9999px;
    opacity: 0;
    transform: scale(0.94);
    pointer-events: none;
}

=======
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
.assessment-circle-button--recording {
    animation: hold-recording-pulse 900ms ease-in-out infinite alternate;
}

.assessment-circle-button--pulse {
    animation: hold-button-syllable-pulse 580ms cubic-bezier(0.15, 0.85, 0.25, 1) infinite;
}

<<<<<<< HEAD
.assessment-circle-button--pulse::before {
    animation: hold-button-syllable-ring 580ms cubic-bezier(0.15, 0.85, 0.25, 1) infinite;
}

@keyframes hold-recording-pulse {
    from {
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1);
        border-color: #3B82F6;
=======
@keyframes hold-recording-pulse {
    from {
        box-shadow:
            0 clamp(0.46rem, min(4.4cqh, 2.7cqw), 0.8rem) 0 #B84B24,
            0 clamp(0.9rem, min(7.4cqh, 4.6cqw), 1.35rem) clamp(0.95rem, min(8cqh, 5cqw), 1.55rem) rgba(54, 83, 101, 0.26);
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
    }
    to {
<<<<<<< HEAD
        box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.18), 0 8px 28px rgba(59, 130, 246, 0.35);
        border-color: #2563EB;
=======
        box-shadow:
            0 clamp(0.46rem, min(4.4cqh, 2.7cqw), 0.8rem) 0 #B84B24,
            0 clamp(0.9rem, min(7.4cqh, 4.6cqw), 1.35rem) clamp(0.95rem, min(8cqh, 5cqw), 1.55rem) rgba(54, 83, 101, 0.22);
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
    }
}

/* Syllable-beat pulse: only box-shadow animates, button stays perfectly circular and centered */
@keyframes hold-button-syllable-pulse {
    0%   { box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1); }
    10%  { box-shadow: 0 0 0 5px rgba(59, 130, 246, 0.28), 0 6px 24px rgba(59, 130, 246, 0.4); }
    22%  { box-shadow: 0 4px 12px rgba(30, 58, 138, 0.08); }
    40%  { box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18), 0 5px 18px rgba(59, 130, 246, 0.28); }
    55%  { box-shadow: 0 4px 12px rgba(30, 58, 138, 0.08); }
    100% { box-shadow: 0 4px 12px rgba(30, 58, 138, 0.1); }
}

<<<<<<< HEAD
@keyframes hold-button-syllable-ring {
    0%   { opacity: 0;    transform: scale(0.94); }
    10%  { opacity: 0.4;  transform: scale(1.0);  }
    35%  { opacity: 0;    transform: scale(1.18); }
    55%  { opacity: 0.18; transform: scale(1.06); }
    80%,
    100% { opacity: 0;    transform: scale(1.22); }
}
=======
>>>>>>> 221b082f91c787ad860240b2aead36f7b517b0b0
</style>
