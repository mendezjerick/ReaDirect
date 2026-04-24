<script setup>
defineProps({ title: { type: String, default: 'Reading Star' } });
</script>

<template>
    <div class="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-base font-black text-text shadow-md shadow-accent/30">
        <span>★</span>
        {{ title }}
    </div>
</template>
