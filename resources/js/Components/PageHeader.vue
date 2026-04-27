<script setup>
defineProps({ title: String, subtitle: { type: String, default: '' } });
</script>

<template>
    <header class="mb-6">
        <h1 class="text-2xl font-extrabold text-text">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 text-sm font-medium text-muted">{{ subtitle }}</p>
    </header>
</template>
