<script setup>
import { computed } from 'vue';
import { BookOpenCheck, Check } from 'lucide-vue-next';
import SyncStatusBadge from '../Components/SyncStatusBadge.vue';
import AdminTestingToolbar from '../Components/Admin/AdminTestingToolbar.vue';
import { diagnosticStepsFor } from '../utils/diagnosticSteps';

const props = defineProps({
    progress: { type: Number, default: 0 },
    steps: { type: Array, default: () => [] },
    diagnosticStep: { type: String, default: '' },
});

const visibleSteps = computed(() => props.steps.length ? props.steps : (props.diagnosticStep ? diagnosticStepsFor(props.diagnosticStep) : []));
</script>

<template>
    <div class="min-h-screen bg-background text-text">
        <AdminTestingToolbar />
        <header class="sticky top-0 z-20 border-b border-border bg-surface/95 backdrop-blur">
            <div class="learner-frame flex items-center gap-4 py-3">
                <a href="/" class="inline-flex shrink-0 items-center gap-3 text-xl font-black text-primary md:text-2xl">
                    <span class="grid size-10 place-items-center rounded-2xl bg-primary-light text-primary">
                        <BookOpenCheck class="size-7" />
                    </span>
                    ReaDirect
                </a>
                <div v-if="visibleSteps.length" class="hidden flex-1 items-start gap-0 px-3 lg:flex">
                    <div
                        v-for="(step, index) in visibleSteps"
                        :key="step.label"
                        class="relative flex flex-1 flex-col items-center gap-2 text-center"
                    >
                        <div
                            v-if="index > 0"
                            class="absolute left-0 top-3 h-1 w-1/2 -translate-x-1/2 rounded-full"
                            :class="visibleSteps[index - 1]?.status === 'complete' ? 'bg-primary' : 'bg-border'"
                            aria-hidden="true"
                        />
                        <div
                            v-if="index < visibleSteps.length - 1"
                            class="absolute right-0 top-3 h-1 w-1/2 translate-x-1/2 rounded-full"
                            :class="step.status === 'complete' ? 'bg-primary' : 'bg-border'"
                            aria-hidden="true"
                        />
                        <span
                            class="relative z-10 grid size-7 place-items-center rounded-full border-4 bg-surface"
                            :class="step.status === 'pending' ? 'border-border text-muted' : 'border-primary text-primary'"
                        >
                            <Check v-if="step.status === 'complete'" class="size-4 stroke-[4]" />
                            <span v-else class="size-2 rounded-full" :class="step.status === 'current' ? 'bg-primary' : 'bg-border'" />
                        </span>
                        <span class="text-sm font-black" :class="step.status === 'pending' ? 'text-muted' : 'text-primary'">
                            {{ step.label }}
                        </span>
                    </div>
                </div>
                <div v-if="visibleSteps.length" class="h-4 flex-1 overflow-hidden rounded-full bg-primary-light lg:hidden">
                    <div class="h-full rounded-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
                </div>
                <div v-else class="h-4 flex-1 overflow-hidden rounded-full bg-primary-light">
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
