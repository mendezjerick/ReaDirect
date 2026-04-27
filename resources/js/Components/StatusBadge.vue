<script setup>
defineProps({
    status:  { type: String, default: 'Ready' },
    variant: { type: String, default: 'primary' },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold whitespace-nowrap"
        :class="{
            'bg-blue-50 text-blue-600':     variant === 'primary',
            'bg-green-50 text-green-600':    variant === 'success',
            'bg-orange-50 text-orange-600':  variant === 'warning',
            'bg-red-50 text-red-600':        variant === 'danger',
        }"
    >{{ status }}</span>
</template>
