<script setup>
defineProps({
    title: { type: String, default: 'Assessment Agent' },
    tone: { type: String, default: 'calm' },
});
</script>

<template>
    <aside class="rounded-[24px] border border-border bg-surface p-5 shadow-md shadow-primary/10">
        <p class="text-sm font-black uppercase tracking-wide text-primary">{{ title }}</p>
        <p class="mt-2 text-lg font-bold text-text">
            <slot />
        </p>
    </aside>
</template>
