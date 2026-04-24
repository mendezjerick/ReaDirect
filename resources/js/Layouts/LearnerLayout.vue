<script setup>
import SyncStatusBadge from '../Components/SyncStatusBadge.vue';

defineProps({
    progress: { type: Number, default: 0 },
});
</script>

<template>
    <div class="min-h-screen bg-background text-text">
        <header class="sticky top-0 z-20 border-b border-border bg-surface/95 backdrop-blur">
            <div class="mx-auto flex max-w-4xl items-center gap-4 px-4 py-4">
                <a href="/" class="text-2xl font-black text-primary">ReaDirect</a>
                <div class="h-4 flex-1 overflow-hidden rounded-full bg-primary-light">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
                </div>
                <SyncStatusBadge />
            </div>
        </header>
        <main class="mx-auto max-w-4xl px-4 pb-28 pt-6">
            <slot />
        </main>
    </div>
</template>
