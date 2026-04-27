<script setup>
import { Inbox } from 'lucide-vue-next';
defineProps({ title: { type: String, default: 'Nothing here yet' }, message: { type: String, default: '' } });
</script>

<template>
    <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border/60 bg-background/50 py-10 text-center">
        <Inbox :size="32" class="text-slate-300" />
        <p class="text-sm font-bold text-text">{{ title }}</p>
        <p v-if="message" class="text-[12px] text-muted max-w-xs">{{ message }}</p>
    </div>
</template>
