<script setup>
defineProps({ type: { type: String, default: 'button' } });
</script>

<template>
    <button
        :type="type"
        class="rd-secondary-button group inline-flex min-h-[48px] items-center justify-center gap-2 px-5 text-[15px] transition-all duration-150 ease-out focus:outline-none focus:ring-4 focus:ring-primary/10"
    >
        <slot />
    </button>
</template>

<style scoped>
.rd-secondary-button {
    border: 2px solid rgba(245, 133, 73, 0.28);
    border-radius: 999px;
    background: var(--rd-card-cream);
    color: var(--rd-primary-orange);
    font-weight: 900;
    letter-spacing: 0.03em;
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.18), 0 8px 16px rgba(35, 55, 70, 0.08);
}

.rd-secondary-button:hover {
    border-color: rgba(245, 133, 73, 0.45);
    background: var(--rd-card-warm);
}

.rd-secondary-button:active {
    transform: translateY(3px);
    box-shadow: 0 1px 0 rgba(111, 101, 52, 0.18), 0 4px 10px rgba(35, 55, 70, 0.08);
}
</style>
