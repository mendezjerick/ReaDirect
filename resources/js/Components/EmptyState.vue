<script setup>
defineProps({ title: { type: String, default: 'Nothing here yet' }, message: { type: String, default: '' } });
</script>

<template>
    <div class="rounded-2xl border border-dashed border-border bg-surface p-8 text-center">
        <p class="text-xl font-black text-text">{{ title }}</p>
        <p class="mt-2 text-muted">{{ message }}</p>
    </div>
</template>
