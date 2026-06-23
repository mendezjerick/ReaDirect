<script setup>
import { useAsrVisualization } from '../Composables/useAsrVisualization';

const { enabled, toastMessage, toastVisible, toggle } = useAsrVisualization();
</script>

<template>
    <div class="relative shrink-0">
        <button
            type="button"
            class="grid size-10 place-items-center rounded-full border text-[13px] font-black tracking-tight shadow-sm transition-all duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            :class="enabled
                ? 'border-primary/40 bg-primary text-white shadow-primary/20 ring-4 ring-primary/10 hover:bg-primary-dark'
                : 'border-slate-200 bg-white text-slate-500 hover:border-primary/30 hover:bg-primary-light hover:text-primary'"
            :aria-pressed="enabled"
            :title="enabled ? 'Disable ASR visualization' : 'Enable ASR visualization'"
            @click="toggle"
        >
            Re
        </button>

        <Transition name="asr-toggle-pop">
            <div
                v-if="toastVisible"
                class="pointer-events-none absolute right-0 top-12 z-50 rounded-full border border-slate-200/80 bg-white px-3 py-1.5 text-xs font-black text-slate-700 shadow-xl shadow-slate-900/10"
            >
                {{ toastMessage }}
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.asr-toggle-pop-enter-active,
.asr-toggle-pop-leave-active {
    transition: opacity 160ms ease, transform 160ms ease;
}

.asr-toggle-pop-enter-from,
.asr-toggle-pop-leave-to {
    opacity: 0;
    transform: translateY(-4px) scale(0.96);
}
</style>
