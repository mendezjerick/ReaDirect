<script setup>
defineProps({ label: String, value: [String, Number], suffix: { type: String, default: '' } });
</script>

<template>
    <article class="rounded-2xl border border-border bg-surface p-5 shadow-md shadow-primary/10">
        <p class="text-sm font-bold text-muted">{{ label }}</p>
        <p class="mt-2 text-3xl font-black text-primary">{{ value }}{{ suffix }}</p>
    </article>
</template>
