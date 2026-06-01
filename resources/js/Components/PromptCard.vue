<script setup>
defineProps({
    label: { type: String, default: '' },
    prompt: { type: String, required: true },
    size: { type: String, default: 'letter' },
});
</script>

<template>
    <section class="prompt-card relative overflow-hidden rounded-[36px] border border-slate-200/80 bg-white p-6 text-center shadow-xl shadow-slate-200/30 md:p-8 xl:p-10">
        <!-- Decorative blobs -->
        <div class="pointer-events-none absolute -left-10 -top-10 h-36 w-36 rounded-full bg-primary/5 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-10 -right-10 h-36 w-36 rounded-full bg-blue-400/5 blur-3xl" />
        <span class="pointer-events-none absolute right-8 top-8 text-3xl font-black text-primary/5" aria-hidden="true">✦</span>

        <p v-if="label" class="prompt-label relative text-[14px] font-black uppercase tracking-widest text-primary/50 md:text-[15px]">{{ label }}</p>
        <div
            :class="{
                'text-7xl md:text-8xl xl:text-9xl': size === 'letter',
                'text-4xl md:text-5xl xl:text-6xl': size === 'word',
                'text-2xl md:text-3xl xl:text-4xl': size === 'sentence',
            }"
            class="prompt-text relative mt-2 bg-gradient-to-br from-slate-900 to-slate-700 bg-clip-text font-black leading-tight text-transparent"
        >
            {{ prompt }}
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
</style>
