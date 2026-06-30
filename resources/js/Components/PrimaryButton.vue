<script setup>
defineProps({
    type: { type: String, default: 'button' },
    disabled: Boolean,
    orangeHover: Boolean,
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="rd-primary-button group inline-flex min-h-[56px] items-center justify-center gap-2 px-7 text-[17px] transition-all duration-150 ease-out disabled:cursor-not-allowed"
        :class="{ 'rd-primary-button--orange-hover': orangeHover }"
    >
        <slot />
    </button>
</template>

<style scoped>
.rd-primary-button {
    border-radius: 999px;
    border: 2px solid #D9652F;
    outline: 0;
    appearance: none;
    -webkit-appearance: none;
    background: linear-gradient(180deg, var(--rd-action-button-light) 0%, var(--rd-action-button) 100%);
    color: #ffffff;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    box-shadow: 0 7px 0 #B84B24, 0 12px 20px rgba(54, 83, 101, 0.25), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}

.rd-primary-button:focus,
.rd-primary-button:focus-visible {
    outline: 0;
}

.rd-primary-button:hover:not(:disabled) {
    background: linear-gradient(180deg, #1A7890 0%, #115A6C 100%);
}

.rd-primary-button--orange-hover:hover:not(:disabled) {
    background: linear-gradient(180deg, var(--rd-action-button-light) 0%, var(--rd-action-button) 100%);
}

.rd-primary-button:active:not(:disabled) {
    transform: translateY(5px);
    box-shadow: 0 2px 0 #B84B24, 0 6px 12px rgba(54, 83, 101, 0.2);
}

.rd-primary-button:disabled {
    border-color: rgba(111, 101, 52, 0.18);
    background: linear-gradient(180deg, #F7D3B0 0%, #F2A65A 100%);
    color: rgba(255, 255, 255, 0.9);
    opacity: 0.82;
    box-shadow: 0 5px 0 rgba(111, 101, 52, 0.2), inset 0 2px 0 rgba(255, 255, 255, 0.35);
}
</style>
