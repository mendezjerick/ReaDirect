<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    prompt: { type: String, required: true },
    attempts: { type: Array, default: () => [] },
    highlightTargets: { type: Array, default: () => [] },
    size: { type: String, default: 'word' },
});

const normalizeTarget = (target) => {
    if (typeof target === 'string') {
        return { text: target, matchCase: false, wholeWord: false };
    }

    return {
        text: String(target?.text ?? ''),
        matchCase: target?.matchCase === true,
        wholeWord: target?.wholeWord === true,
    };
};

const isBoundary = (text, index) => !/[A-Za-z0-9]/.test(text[index] ?? '');

const findTarget = (text, target) => {
    const needle = String(target.text ?? '');

    if (!needle) return null;

    const haystack = target.matchCase ? text : text.toLowerCase();
    const query = target.matchCase ? needle : needle.toLowerCase();
    let index = haystack.indexOf(query);

    while (index !== -1) {
        if (!target.wholeWord || (isBoundary(text, index - 1) && isBoundary(text, index + needle.length))) {
            return { index, length: needle.length };
        }

        index = haystack.indexOf(query, index + 1);
    }

    return null;
};

const promptSegments = computed(() => {
    const promptText = String(props.prompt ?? '');
    const targets = props.highlightTargets.map(normalizeTarget).filter((target) => target.text);

    if (!promptText || targets.length === 0) {
        return [{ text: promptText, highlighted: false }];
    }

    const segments = [];
    let remaining = promptText;

    while (remaining) {
        const match = targets
            .map((target) => findTarget(remaining, target))
            .filter(Boolean)
            .sort((a, b) => a.index - b.index || b.length - a.length)[0];

        if (!match) {
            segments.push({ text: remaining, highlighted: false });
            break;
        }

        if (match.index > 0) {
            segments.push({ text: remaining.slice(0, match.index), highlighted: false });
        }

        segments.push({ text: remaining.slice(match.index, match.index + match.length), highlighted: true });
        remaining = remaining.slice(match.index + match.length);
    }

    return segments;
});

const promptDensity = computed(() => {
    const text = String(props.prompt ?? '').trim();
    const wordCount = text ? text.split(/\s+/).length : 0;

    if (text.length > 72 || wordCount > 9) return 'long';
    if (text.length > 34 || wordCount > 4) return 'medium';
    if (props.size === 'letter' && text.length > 3) return 'medium';

    return 'short';
});

const wrongAttempts = computed(() => props.attempts
    .filter((attempt) => attempt?.is_correct !== true)
    .map((attempt) => String(attempt?.answer ?? '').trim())
    .filter(Boolean)
    .slice(-3));

const stackStyle = computed(() => ({
    '--attempt-count': wrongAttempts.value.length,
}));
</script>

<template>
    <div
        class="assessment-prompt-attempt-stack"
        :class="[`assessment-prompt-attempt-stack--${size}`, `assessment-prompt-attempt-stack--${promptDensity}`]"
        :style="stackStyle"
    >
        <p v-if="label" class="assessment-prompt-attempt-stack-label">{{ label }}</p>

        <TransitionGroup
            name="assessment-prompt-stack"
            tag="div"
            class="assessment-prompt-attempt-stack-body"
        >
            <p key="item" class="assessment-prompt-attempt-stack-main">
                <template v-for="(segment, index) in promptSegments" :key="index">
                    <mark v-if="segment.highlighted" class="assessment-prompt-attempt-stack-mark">{{ segment.text }}</mark>
                    <span v-else>{{ segment.text }}</span>
                </template>
            </p>

            <p
                v-for="(attempt, index) in wrongAttempts"
                :key="`wrong-${index}-${attempt}`"
                class="assessment-prompt-attempt-stack-wrong"
            >
                {{ attempt }}
            </p>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.assessment-prompt-attempt-stack {
    display: grid;
    width: min(100%, 58rem);
    height: 100%;
    max-height: 100%;
    min-width: 0;
    align-content: center;
    justify-items: center;
    gap: clamp(0.28rem, 0.8cqh, 0.62rem);
    overflow: hidden;
    padding-inline: clamp(0rem, 1cqw, 0.5rem);
    text-align: center;
    transform: translateY(calc(var(--attempt-count, 0) * -0.16rem));
    transition: transform 220ms ease;
}

.assessment-prompt-attempt-stack-label {
    margin: 0;
    font-size: clamp(0.72rem, 1.55dvh, 0.95rem);
    font-weight: 900;
    letter-spacing: 0;
    color: var(--rd-text-muted);
}

.assessment-prompt-attempt-stack-body {
    display: flex;
    width: 100%;
    min-width: 0;
    max-height: 100%;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: clamp(0.2rem, 0.85cqh, 0.58rem);
}

.assessment-prompt-attempt-stack-main,
.assessment-prompt-attempt-stack-wrong {
    margin: 0;
    max-width: 100%;
    min-width: 0;
    font-weight: 900;
    line-height: 1.12;
    overflow-wrap: anywhere;
    text-wrap: balance;
    word-break: normal;
}

.assessment-prompt-attempt-stack-main {
    color: #000000;
    font-size: var(--stack-main-font-size);
}

.assessment-prompt-attempt-stack-wrong {
    color: var(--rd-result-wrong, #692721);
    font-size: var(--stack-wrong-font-size);
    line-height: 1.15;
    opacity: 0.9;
}

.assessment-prompt-attempt-stack--letter {
    --stack-main-font-size: clamp(5.4rem, min(56cqh, 15cqw), 10rem);
    --stack-wrong-font-size: clamp(1.2rem, min(12cqh, 4.2cqw), 2.8rem);
}

.assessment-prompt-attempt-stack--word {
    --stack-main-font-size: clamp(1.9rem, min(34cqh, 9.4cqw), 4.8rem);
    --stack-wrong-font-size: clamp(1rem, min(11cqh, 3.4cqw), 2.05rem);
}

.assessment-prompt-attempt-stack--sentence {
    --stack-main-font-size: clamp(1.25rem, min(18cqh, 5cqw), 2.9rem);
    --stack-wrong-font-size: clamp(0.82rem, min(7.5cqh, 2.65cqw), 1.35rem);
}

.assessment-prompt-attempt-stack--medium {
    --stack-main-font-size: clamp(1.05rem, min(13cqh, 4.1cqw), 2.35rem);
    --stack-wrong-font-size: clamp(0.78rem, min(6.5cqh, 2.2cqw), 1.18rem);
}

.assessment-prompt-attempt-stack--long {
    --stack-main-font-size: clamp(0.9rem, min(9cqh, 3cqw), 1.65rem);
    --stack-wrong-font-size: clamp(0.7rem, min(5cqh, 1.75cqw), 1rem);
}

.assessment-prompt-attempt-stack-mark {
    border-radius: 0.75rem;
    background: rgba(238, 193, 112, 0.42);
    padding: 0 0.32em;
    color: inherit;
    box-shadow: inset 0 0 0 1px rgba(238, 193, 112, 0.62);
}

.assessment-prompt-stack-enter-active,
.assessment-prompt-stack-leave-active,
.assessment-prompt-stack-move {
    transition: transform 220ms ease, opacity 180ms ease;
}

.assessment-prompt-stack-enter-from {
    opacity: 0;
    transform: translateY(0.55rem);
}

.assessment-prompt-stack-leave-to {
    opacity: 0;
    transform: translateY(-0.35rem);
}
</style>
