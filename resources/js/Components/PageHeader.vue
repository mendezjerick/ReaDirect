<script setup>
defineProps({ title: String, subtitle: { type: String, default: '' } });
</script>

<template>
    <header class="mb-6">
        <p v-if="subtitle" class="text-sm font-black uppercase text-primary">{{ subtitle }}</p>
        <h1 class="text-3xl font-black text-text md:text-4xl">{{ title }}</h1>
    </header>
</template>
