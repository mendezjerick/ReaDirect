<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    prompt: { type: String, required: true },
    size: { type: String, default: 'letter' },
    highlightTargets: { type: Array, default: () => [] },
    illustration: { type: String, default: '' },
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
</script>

<template>
    <section class="prompt-card relative overflow-hidden rounded-[36px] border border-slate-200/80 bg-white p-6 text-center shadow-xl shadow-slate-200/30 md:p-8 xl:p-10">
        <!-- Decorative blobs -->
        <div class="pointer-events-none absolute -left-10 -top-10 h-36 w-36 rounded-full bg-primary/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 -right-10 h-36 w-36 rounded-full bg-blue-400/5 blur-3xl" />
        <span class="pointer-events-none absolute right-8 top-8 text-3xl font-black text-primary/5" aria-hidden="true">✦</span>

        <!-- Illustration image (word or passage visual) -->
        <div v-if="illustration" class="prompt-illustration relative mx-auto mb-3 flex items-center justify-center">
            <div class="illustration-glow pointer-events-none absolute inset-0 rounded-[28px] bg-primary/5 blur-2xl" />
            <img
                :src="illustration"
                :alt="`Illustration for ${prompt}`"
                class="relative h-[140px] w-[140px] rounded-[28px] object-contain drop-shadow-lg md:h-[160px] md:w-[160px]"
            >
        </div>

        <p v-if="label" class="prompt-label relative text-[14px] font-black uppercase tracking-widest text-primary/50 md:text-[15px]">{{ label }}</p>
        <div
            :class="{
                'text-7xl md:text-8xl xl:text-9xl': size === 'letter',
                'text-4xl md:text-5xl xl:text-6xl': size === 'word',
                'text-2xl md:text-3xl xl:text-4xl': size === 'sentence',
            }"
            class="prompt-text relative mt-2 font-black leading-tight"
        >
            <template v-for="(segment, index) in promptSegments" :key="index">
                <mark
                    v-if="segment.highlighted"
                    class="rounded-2xl bg-gradient-to-r from-amber-100 to-yellow-100 px-3 py-1 text-slate-800 ring-1 ring-amber-200/50"
                >{{ segment.text }}</mark>
                <span v-else class="bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text text-transparent">{{ segment.text }}</span>
            </template>
        </div>
    </section>
</template>

<style scoped>
.prompt-card {
    animation: cardSpring 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
}
@keyframes cardSpring {
    from { opacity: 0; transform: scale(0.92) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.prompt-label {
    animation: labelFade 0.5s ease-out forwards;
    animation-delay: 0.15s;
    opacity: 0;
}
@keyframes labelFade {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.prompt-text {
    animation: textPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.25s;
    opacity: 0;
}
@keyframes textPop {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.prompt-illustration {
    animation: illustrationBounce 0.65s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes illustrationBounce {
    from { opacity: 0; transform: scale(0.6) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.illustration-glow {
    animation: glowPulse 3s ease-in-out infinite alternate;
}
@keyframes glowPulse {
    from { opacity: 0.4; transform: scale(0.9); }
    to { opacity: 0.8; transform: scale(1.05); }
}
</style>
