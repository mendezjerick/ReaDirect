<script setup>
defineProps({ type: { type: String, default: 'button' } });
</script>

<template>
    <button :type="type" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-danger px-5 text-base font-black text-white shadow-lg shadow-danger/20">
        <slot />
    </button>
</template>
