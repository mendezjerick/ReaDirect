<script setup>
defineProps({ type: { type: String, default: 'button' } });
</script>

<template>
    <button
        :type="type"
        class="group inline-flex min-h-[48px] items-center justify-center gap-2 rounded-[18px] border-2 border-primary/20 bg-white px-5 text-[15px] font-black text-primary shadow-sm transition-all duration-200 ease-out hover:-translate-y-0.5 hover:scale-[1.02] hover:border-primary/30 hover:bg-primary/5 hover:shadow-md focus:outline-none focus:ring-4 focus:ring-primary/10 active:scale-[0.98]"
    >
        <slot />
    </button>
</template>
