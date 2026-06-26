<script setup>
defineProps({ type: { type: String, default: 'button' }, disabled: Boolean });
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="rd-primary-button group inline-flex min-h-[56px] items-center justify-center gap-2 px-7 text-[17px] transition-all duration-150 ease-out disabled:cursor-not-allowed"
    >
        <slot />
    </button>
</template>

<style scoped>
.rd-primary-button {
    border-radius: 999px;
    border: 0;
    outline: 0;
    appearance: none;
    -webkit-appearance: none;
    background: linear-gradient(180deg, var(--rd-action-button-light) 0%, var(--rd-action-button) 100%);
    color: #ffffff;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    box-shadow: 0 7px 0 var(--rd-action-button-dark), 0 10px 16px var(--rd-action-button-shadow);
}

.rd-primary-button:focus,
.rd-primary-button:focus-visible {
    outline: 0;
}

.rd-primary-button:hover:not(:disabled) {
    background: linear-gradient(180deg, #1A7890 0%, #115A6C 100%);
}

.rd-primary-button:active:not(:disabled) {
    transform: translateY(5px);
    box-shadow: 0 2px 0 var(--rd-action-button-dark), 0 5px 10px rgba(8, 49, 61, 0.18);
}

.rd-primary-button:disabled {
    background: linear-gradient(180deg, #71919A 0%, #557781 100%);
    opacity: 0.65;
    box-shadow: 0 6px 0 rgba(8, 49, 61, 0.32);
}
</style>
