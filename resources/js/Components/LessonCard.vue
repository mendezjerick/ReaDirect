<script setup>
import { BookOpen } from 'lucide-vue-next';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    active: Boolean,
});
</script>

<template>
    <article
        class="rounded-[32px] border bg-white p-6 shadow-lg shadow-slate-200/30 transition-all duration-200 xl:p-7"
        :class="active ? 'border-blue-400 ring-4 ring-blue-100/50' : 'border-slate-200/80'"
    >
        <div class="flex items-start gap-4 xl:gap-5">
            <div class="grid size-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-sky-400 to-blue-500 text-white shadow-md shadow-blue-500/20 ring-1 ring-white/20 xl:size-16">
                <slot name="icon">
                    <BookOpen class="size-6 xl:size-7" stroke-width="2.5" />
                </slot>
            </div>
            <div class="min-w-0">
                <h3 class="text-xl font-black text-slate-800 xl:text-2xl">{{ title }}</h3>
                <p class="mt-1.5 text-base font-semibold leading-relaxed text-slate-500 xl:text-lg">{{ description }}</p>
            </div>
        </div>
    </article>
</template>
