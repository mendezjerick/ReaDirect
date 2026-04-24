<script setup>
defineProps({ type: { type: String, default: 'button' }, disabled: Boolean });
</script>

<template>
    <button :type="type" :disabled="disabled" class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-primary px-6 text-lg font-black text-white shadow-lg shadow-primary/20 hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60">
        <slot />
    </button>
</template>
