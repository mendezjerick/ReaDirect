<script setup>
defineProps({ days: { type: Number, default: 1 } });
</script>

<template>
    <article class="rounded-2xl border border-border bg-surface p-5 shadow-md shadow-primary/10">
        <p class="text-sm font-bold text-muted">Practice streak</p>
        <p class="mt-2 text-3xl font-black text-primary">{{ days }} day{{ days === 1 ? '' : 's' }}</p>
    </article>
</template>
