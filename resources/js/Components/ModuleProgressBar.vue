<script setup>
defineProps({ value: { type: Number, default: 0 } });
</script>

<template>
    <div class="h-5 overflow-hidden rounded-full bg-primary-light">
        <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${value}%` }" />
    </div>
</template>
