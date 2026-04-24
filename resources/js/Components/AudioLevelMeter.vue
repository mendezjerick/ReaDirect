<script setup>
defineProps({ level: { type: Number, default: 35 } });
</script>

<template>
    <div class="h-4 overflow-hidden rounded-full bg-primary-light">
        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${level}%` }" />
    </div>
</template>
