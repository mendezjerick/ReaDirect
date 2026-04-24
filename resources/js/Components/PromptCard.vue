<script setup>
defineProps({
    label: { type: String, default: '' },
    prompt: { type: String, required: true },
    size: { type: String, default: 'letter' },
});
</script>

<template>
    <section class="rounded-[32px] border border-border bg-surface p-8 text-center shadow-xl shadow-primary/10">
        <p v-if="label" class="mb-4 text-lg font-black text-muted">{{ label }}</p>
        <div :class="{
            'text-8xl md:text-9xl': size === 'letter',
            'text-5xl md:text-7xl': size === 'word',
            'text-3xl md:text-4xl': size === 'sentence',
        }" class="font-black leading-tight text-text">
            {{ prompt }}
        </div>
    </section>
</template>
