<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    prompt: { type: String, required: true },
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
</script>

<template>
    <div class="assessment-prompt-text" :class="[`assessment-prompt-text--${size}`, `assessment-prompt-text--${promptDensity}`]">
        <p v-if="label" class="assessment-prompt-text-label">{{ label }}</p>
        <p class="assessment-prompt-text-body">
            <template v-for="(segment, index) in promptSegments" :key="index">
                <mark v-if="segment.highlighted" class="assessment-prompt-text-mark">{{ segment.text }}</mark>
                <span v-else>{{ segment.text }}</span>
            </template>
        </p>
    </div>
</template>

<style scoped>
.assessment-prompt-text {
    display: grid;
    width: min(100%, 58rem);
    height: 100%;
    max-height: 100%;
    min-width: 0;
    grid-template-rows: auto minmax(0, auto);
    align-content: center;
    justify-items: center;
    gap: clamp(0.4rem, 1.1dvh, 0.8rem);
    overflow: hidden;
    padding-inline: clamp(0rem, 1cqw, 0.5rem);
    text-align: center;
}

.assessment-prompt-text-label {
    margin: 0;
    font-size: clamp(0.78rem, 1.7dvh, 1rem);
    font-weight: 900;
    letter-spacing: 0;
    color: rgb(148 163 184);
}

.assessment-prompt-text-body {
    margin: 0;
    max-width: 100%;
    min-width: 0;
    color: rgb(30 41 59);
    font-size: var(--prompt-font-size);
    font-weight: 900;
    line-height: 1.12;
    overflow-wrap: anywhere;
    text-wrap: balance;
    word-break: normal;
}

.assessment-prompt-text--letter .assessment-prompt-text-body {
    --prompt-font-size: clamp(3.4rem, min(70cqh, 18cqw), 12rem);
    line-height: 0.9;
}

.assessment-prompt-text--word .assessment-prompt-text-body {
    --prompt-font-size: clamp(2rem, min(42cqh, 11cqw), 5.2rem);
}

.assessment-prompt-text--sentence .assessment-prompt-text-body {
    --prompt-font-size: clamp(1.35rem, min(24cqh, 5.8cqw), 3.2rem);
    line-height: 1.18;
}

.assessment-prompt-text--medium .assessment-prompt-text-body {
    --prompt-font-size: clamp(1.2rem, min(16cqh, 4.6cqw), 2.7rem);
    line-height: 1.15;
}

.assessment-prompt-text--long .assessment-prompt-text-body {
    --prompt-font-size: clamp(0.95rem, min(11cqh, 3.4cqw), 1.9rem);
    line-height: 1.18;
}

.assessment-prompt-text-mark {
    border-radius: 0.75rem;
    background: rgb(254 243 199);
    padding: 0 0.32em;
    color: rgb(30 41 59);
    box-shadow: inset 0 0 0 1px rgb(252 211 77 / 0.5);
}
</style>
