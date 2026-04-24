<script setup>
defineProps({
    title: { type: String, required: true },
    status: { type: String, default: 'locked' },
    number: { type: Number, required: true },
});
</script>

<template>
    <div class="flex items-center gap-4">
        <div class="grid size-16 place-items-center rounded-full text-xl font-black shadow-lg" :class="{
            'bg-primary text-white shadow-primary/30': status === 'active',
            'bg-success text-white shadow-success/20': status === 'complete',
            'bg-surface text-muted border-2 border-border': status === 'locked',
        }">
            {{ status === 'complete' ? '✓' : number }}
        </div>
        <div class="min-w-0">
            <p class="text-lg font-black text-text">{{ title }}</p>
            <p class="text-sm font-bold text-muted">{{ status }}</p>
        </div>
    </div>
</template>
