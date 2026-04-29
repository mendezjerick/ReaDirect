<script setup>
import SyncStatusBadge from '../Components/SyncStatusBadge.vue';
import AdminTestingToolbar from '../Components/Admin/AdminTestingToolbar.vue';

defineProps({
    progress: { type: Number, default: 0 },
});
</script>

<template>
    <div class="min-h-screen bg-background text-text">
        <AdminTestingToolbar />
        <header class="sticky top-0 z-20 border-b border-border bg-surface/95 backdrop-blur">
            <div class="learner-frame flex items-center gap-3 py-3">
                <a href="/" class="text-xl font-black text-primary md:text-2xl">ReaDirect</a>
                <div class="h-4 flex-1 overflow-hidden rounded-full bg-primary-light">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
                </div>
                <SyncStatusBadge />
            </div>
        </header>
        <main class="learner-frame learner-stage">
            <div v-if="$slots.agent" class="learner-stage-grid">
                <aside class="sticky top-[68px] z-10 max-h-[calc(100vh-132px)] overflow-visible lg:top-20">
                    <slot name="agent" />
                </aside>
                <div class="learner-content min-w-0">
                    <slot />
                </div>
            </div>
            <div v-else class="learner-content">
                <slot />
            </div>
        </main>
    </div>
</template>
