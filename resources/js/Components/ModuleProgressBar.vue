<script setup>
defineProps({ value: { type: Number, default: 0 } });
</script>

<template>
    <div class="h-3 overflow-hidden rounded-full bg-slate-200/60 shadow-inner xl:h-3.5">
        <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-blue-500 transition-all duration-500" :style="{ width: `${value}%` }" />
    </div>
</template>
