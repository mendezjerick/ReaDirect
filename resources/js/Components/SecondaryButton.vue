<script setup>
defineProps({ type: { type: String, default: 'button' } });
</script>

<template>
    <button :type="type" class="inline-flex min-h-12 items-center justify-center rounded-2xl border-2 border-primary bg-surface px-5 text-base font-black text-primary hover:bg-primary-light">
        <slot />
    </button>
</template>
