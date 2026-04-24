<script setup>
import { Mic, Square, RotateCcw } from 'lucide-vue-next';

defineProps({
    state: { type: String, default: 'ready' },
});

const labels = {
    ready: 'Start recording',
    recording: 'Recording',
    processing: 'Checking',
    done: 'Done',
    retry: 'Try again',
};
</script>

<template>
    <button class="mx-auto grid size-36 place-items-center rounded-full text-white shadow-2xl" :class="{
        'bg-primary shadow-primary/30': state === 'ready',
        'bg-warning shadow-warning/30 animate-pulse': state === 'recording',
        'bg-primary-dark shadow-primary/30': state === 'processing',
        'bg-success shadow-success/30': state === 'done',
        'bg-accent text-text shadow-accent/30': state === 'retry',
    }">
        <span class="grid justify-items-center gap-2 text-lg font-black">
            <Square v-if="state === 'recording'" class="size-9" />
            <RotateCcw v-else-if="state === 'retry'" class="size-9" />
            <Mic v-else class="size-9" />
            {{ labels[state] }}
        </span>
    </button>
</template>
