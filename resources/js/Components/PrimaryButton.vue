<script setup>
defineProps({ type: { type: String, default: 'button' }, disabled: Boolean });
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="group inline-flex min-h-[56px] items-center justify-center gap-2 rounded-[20px] bg-gradient-to-r from-primary to-blue-600 px-7 text-[17px] font-black text-white shadow-xl shadow-primary/20 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:shadow-2xl hover:shadow-primary/30 focus:outline-none focus:ring-4 focus:ring-primary/20 active:scale-[0.98] active:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:scale-100 disabled:hover:shadow-xl"
    >
        <slot />
    </button>
</template>
