<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { BookOpenCheck, Check, ArrowLeft } from 'lucide-vue-next';
import SyncStatusBadge from '../Components/SyncStatusBadge.vue';
import AdminTestingToolbar from '../Components/Admin/AdminTestingToolbar.vue';
import { diagnosticStepsFor } from '../utils/diagnosticSteps';

const props = defineProps({
    progress: { type: Number, default: 0 },
    steps: { type: Array, default: () => [] },
    diagnosticStep: { type: String, default: '' },
    backUrl: { type: String, default: '' },
    backLabel: { type: String, default: 'Back' },
});

const visibleSteps = computed(() => props.steps.length ? props.steps : (props.diagnosticStep ? diagnosticStepsFor(props.diagnosticStep) : []));
</script>

<template>
    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-blue-50/30 text-text">
        <AdminTestingToolbar />
        <header class="anim-header sticky top-0 z-20 border-b border-blue-100/60 bg-white/90 backdrop-blur-lg">
            <div class="learner-frame flex items-center gap-3 py-3 md:gap-4">
                <Link v-if="backUrl" :href="backUrl" class="group flex shrink-0 items-center justify-center rounded-full bg-slate-100 p-2.5 text-slate-500 transition-all hover:bg-slate-200 hover:text-slate-800" :title="backLabel">
                    <ArrowLeft class="size-5 transition-transform group-hover:-translate-x-0.5 md:size-6" />
                </Link>

                <a href="/" class="group inline-flex shrink-0 items-center gap-2.5 text-xl font-black text-primary transition-all hover:scale-[1.02] md:text-2xl">
                    <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white shadow-md shadow-primary/20 transition-shadow group-hover:shadow-lg group-hover:shadow-primary/30">
                        <BookOpenCheck class="size-6" />
                    </span>
                    <span class="hidden sm:inline">ReaDirect</span>
                </a>
                <div v-if="visibleSteps.length" class="hidden flex-1 items-start gap-0 px-3 lg:flex">
                    <div
                        v-for="(step, index) in visibleSteps"
                        :key="step.label"
                        class="relative flex flex-1 flex-col items-center gap-2 text-center"
                    >
                        <div
                            v-if="index > 0"
                            class="absolute left-0 top-3.5 h-[3px] w-1/2 -translate-x-1/2 rounded-full transition-colors duration-300"
                            :class="visibleSteps[index - 1]?.status === 'complete' ? 'bg-primary' : 'bg-slate-200'"
                            aria-hidden="true"
                        />
                        <div
                            v-if="index < visibleSteps.length - 1"
                            class="absolute right-0 top-3.5 h-[3px] w-1/2 translate-x-1/2 rounded-full transition-colors duration-300"
                            :class="step.status === 'complete' ? 'bg-primary' : 'bg-slate-200'"
                            aria-hidden="true"
                        />
                        <span
                            class="relative z-10 grid size-7 place-items-center rounded-full border-[3px] transition-all duration-300"
                            :class="step.status === 'pending'
                                ? 'border-slate-200 bg-white text-slate-300'
                                : step.status === 'current'
                                    ? 'border-primary bg-primary/10 text-primary shadow-sm shadow-primary/20'
                                    : 'border-primary bg-primary text-white shadow-sm shadow-primary/20'"
                        >
                            <Check v-if="step.status === 'complete'" class="size-4 stroke-[4]" />
                            <span v-else-if="step.status === 'current'" class="size-2.5 rounded-full bg-primary" />
                            <span v-else class="size-2 rounded-full bg-slate-200" />
                        </span>
                        <span class="text-[12px] font-bold" :class="step.status === 'pending' ? 'text-slate-400' : 'text-primary'">
                            {{ step.label }}
                        </span>
                    </div>
                </div>
                <div v-if="visibleSteps.length" class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100 shadow-inner lg:hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500" :style="{ width: `${progress}%` }" />
                </div>
                <div v-else class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                    <div class="h-full rounded-full bg-gradient-to-r from-primary to-blue-500 shadow-sm shadow-primary/30 transition-all duration-500" :style="{ width: `${progress}%` }" />
                </div>
                <SyncStatusBadge />
            </div>
        </header>
        <main class="learner-frame learner-stage">
            <div v-if="$slots.agent" class="learner-stage-grid">
                <aside class="flex flex-col gap-4 pb-4 lg:sticky lg:top-20 lg:z-10 lg:max-h-[calc(100vh-140px)] lg:overflow-y-auto lg:px-2 lg:-mx-2 lg:pb-8">
                    <slot name="agent" />
                    <div id="teleport-audio-review" class="empty:hidden"></div>
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

<style scoped>
.anim-header {
    animation: headerFade 0.4s ease-out forwards;
}
@keyframes headerFade {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
