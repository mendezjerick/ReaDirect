<script setup>
defineProps({
    status:  { type: String, default: 'Ready' },
    variant: { type: String, default: 'primary' },
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-widest whitespace-nowrap ring-1 xl:text-[12px]"
        :class="{
            'bg-blue-50 text-blue-600 ring-blue-200/60':       variant === 'primary',
            'bg-green-50 text-green-600 ring-green-200/60':    variant === 'success',
            'bg-orange-50 text-orange-600 ring-orange-200/60': variant === 'warning',
            'bg-red-50 text-red-600 ring-red-200/60':          variant === 'danger',
        }"
    >{{ status }}</span>
</template>
