<script setup>
import { MessageCircle } from 'lucide-vue-next';

defineProps({
    title: { type: String, default: 'Miss Vivian' },
    tone: { type: String, default: 'calm' },
});
</script>

<template>
    <aside class="agent-panel relative overflow-hidden rounded-[28px] border border-slate-200/80 bg-white p-5 shadow-lg shadow-slate-200/30">
        <div class="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-primary/5 blur-2xl" />
        <div class="flex items-center gap-2.5">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-sm">
                <MessageCircle class="size-4" />
            </span>
            <p class="text-[13px] font-black uppercase tracking-widest text-primary">{{ title }}</p>
        </div>
        <p class="relative mt-3 text-[17px] font-bold leading-relaxed text-slate-700">
            <slot />
        </p>
    </aside>
</template>

<style scoped>
.agent-panel {
    animation: panelSlide 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: 0.1s;
    opacity: 0;
}
@keyframes panelSlide {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
