<script setup>
defineProps({ status: { type: String, default: 'Ready' }, variant: { type: String, default: 'primary' } });
</script>

<template>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-black" :class="{
        'bg-primary-light text-primary': variant === 'primary',
        'bg-green-100 text-success': variant === 'success',
        'bg-orange-100 text-warning': variant === 'warning',
        'bg-red-100 text-danger': variant === 'danger',
    }">{{ status }}</span>
</template>
