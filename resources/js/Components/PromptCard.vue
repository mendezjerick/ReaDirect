<script setup>
defineProps({
    label: { type: String, default: '' },
    prompt: { type: String, required: true },
    size: { type: String, default: 'letter' },
});
</script>

<template>
    <section class="rounded-[28px] border border-border bg-surface p-5 text-center shadow-xl shadow-primary/10 md:p-6">
        <p v-if="label" class="mb-2 text-base font-black text-muted md:text-lg">{{ label }}</p>
        <div :class="{
            'text-7xl md:text-8xl xl:text-9xl': size === 'letter',
            'text-4xl md:text-5xl xl:text-6xl': size === 'word',
            'text-2xl md:text-3xl xl:text-4xl': size === 'sentence',
        }" class="font-black leading-tight text-text">
            {{ prompt }}
        </div>
    </section>
</template>
