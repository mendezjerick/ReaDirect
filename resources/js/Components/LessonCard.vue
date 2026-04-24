<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    active: Boolean,
});
</script>

<template>
    <article class="rounded-[28px] border bg-surface p-5 shadow-lg shadow-primary/10" :class="active ? 'border-primary' : 'border-border'">
        <div class="flex items-start gap-4">
            <div class="grid size-14 shrink-0 place-items-center rounded-2xl bg-primary-light text-2xl font-black text-primary">
                <slot name="icon">✓</slot>
            </div>
            <div>
                <h3 class="text-xl font-black text-text">{{ title }}</h3>
                <p class="mt-1 text-base text-muted">{{ description }}</p>
            </div>
        </div>
    </article>
</template>
