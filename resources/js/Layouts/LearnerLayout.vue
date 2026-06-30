<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, BookOpenCheck, Check, Home } from 'lucide-vue-next';
import SyncStatusBadge from '../Components/SyncStatusBadge.vue';
import AdminTestingToolbar from '../Components/Admin/AdminTestingToolbar.vue';
import AsrVisualizationToggle from '../Components/AsrVisualizationToggle.vue';
import { diagnosticStepsFor } from '../utils/diagnosticSteps';

const props = defineProps({
    progress: { type: Number, default: 0 },
    steps: { type: Array, default: () => [] },
    diagnosticStep: { type: String, default: '' },
    backUrl: { type: String, default: '' },
    backLabel: { type: String, default: 'Back' },
    assessmentTask: { type: Boolean, default: false },
    hasBottomBar: { type: Boolean, default: true },
});

const page = usePage();
const qaViewportStorageKey = 'readirect_qa_viewport_mode';
const qaViewportDimensions = {
    desktop: { width: 1366, height: 768 },
    tablet: { width: 768, height: 1024 },
    'mobile-vertical': { width: 390, height: 844 },
    'mobile-horizontal': { width: 844, height: 390 },
};
const validQaViewportModes = ['auto', ...Object.keys(qaViewportDimensions)];

const readQaViewportMode = () => {
    if (typeof window === 'undefined') return 'auto';

    try {
        const stored = window.localStorage.getItem(qaViewportStorageKey);

        return validQaViewportModes.includes(stored) ? stored : 'auto';
    } catch {
        return 'auto';
    }
};

const isQaViewportEmbed = typeof window !== 'undefined'
    && new URLSearchParams(window.location.search).get('qa_viewport_embed') === '1';
const qaViewportMode = ref(readQaViewportMode());
const isTestingMode = computed(() => page.props.adminTesting?.enabled === true);
const visibleSteps = computed(() => props.steps.length ? props.steps : (props.diagnosticStep ? diagnosticStepsFor(props.diagnosticStep) : []));
const forcedQaViewportDimensions = computed(() => qaViewportDimensions[qaViewportMode.value] ?? null);
const shouldRenderQaViewportIframe = computed(() => (
    isTestingMode.value
    && !isQaViewportEmbed
    && qaViewportMode.value !== 'auto'
    && forcedQaViewportDimensions.value !== null
));
const qaViewportIframeStyle = computed(() => {
    const dimensions = forcedQaViewportDimensions.value;

    if (!dimensions) return {};

    return {
        width: `${dimensions.width}px`,
        height: `${dimensions.height}px`,
    };
});
const qaViewportIframeSrc = computed(() => {
    if (typeof window === 'undefined') return '';

    const url = new URL(window.location.href);
    url.searchParams.set('qa_viewport_embed', '1');
    url.searchParams.delete('qa_viewport_mode');

    return url.toString();
});

const syncQaViewportMode = (event = null) => {
    const mode = event?.detail?.mode ?? readQaViewportMode();

    qaViewportMode.value = validQaViewportModes.includes(mode) ? mode : 'auto';
};

onMounted(() => {
    syncQaViewportMode();
    window.addEventListener('readirect:qa-viewport-change', syncQaViewportMode);
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('readirect:qa-viewport-change', syncQaViewportMode);
    }
});
</script>

<template>
    <div
        class="text-text"
        :class="assessmentTask ? 'learner-activity-shell flex min-h-screen flex-col overflow-x-hidden overflow-y-auto' : 'min-h-screen bg-gradient-to-b from-slate-50 to-orange-50/30'"
    >
        <AdminTestingToolbar v-if="!isQaViewportEmbed" />
        <div
            v-if="shouldRenderQaViewportIframe"
            class="qa-viewport-stage qa-viewport-stage--iframe"
            :class="assessmentTask ? 'min-h-0 flex flex-1' : ''"
        >
            <iframe
                :key="qaViewportIframeSrc"
                class="qa-viewport-frame qa-viewport-iframe"
                :src="qaViewportIframeSrc"
                :style="qaViewportIframeStyle"
                title="QA viewport preview"
            />
        </div>
        <div
            v-else
            class="qa-viewport-stage"
            :class="assessmentTask ? 'min-h-0 flex flex-1' : ''"
        >
            <div
                class="qa-viewport-frame"
                :class="assessmentTask ? 'min-h-0 flex flex-1 flex-col' : ''"
            >
                <header
                    v-if="assessmentTask"
                    class="anim-header z-20 flex-none pb-2 pt-1"
                >
                    <div class="learner-frame rd-learner-assessment-header">
                        <div class="rd-learner-assessment-header-face flex min-h-16 items-center gap-3 px-5 py-2">
                            <a href="/" class="group inline-flex shrink-0 items-center gap-2.5 text-xl font-black text-primary transition-all hover:scale-[1.02] md:text-2xl">
                                <span class="grid size-11 place-items-center rounded-xl border-2 border-[#D9652F] bg-primary text-sm font-black text-white shadow-[0_5px_0_#B84B24,0_8px_14px_rgba(54,83,101,0.18),inset_0_2px_0_rgba(255,255,255,0.35)]">
                                    Re
                                </span>
                                <span class="text-text">ReaDirect</span>
                            </a>

                            <div class="ml-auto flex items-center gap-2">
                                <AsrVisualizationToggle />
                                <Link
                                    href="/learner/dashboard"
                                    class="rd-learner-header-icon grid size-10 place-items-center rounded-full transition"
                                    title="Home"
                                    aria-label="Home"
                                >
                                    <Home class="size-5" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </header>
                <header v-else class="anim-header relative z-20 border-b border-orange-100/60 bg-white/90 backdrop-blur-lg">
                    <div class="learner-frame flex items-center gap-3 py-3 md:gap-4">
                        <Link v-if="backUrl" :href="backUrl" class="group flex shrink-0 items-center justify-center rounded-full bg-slate-100 p-2.5 text-slate-500 transition-all hover:bg-slate-200 hover:text-slate-800" :title="backLabel">
                            <ArrowLeft class="size-5 transition-transform group-hover:-translate-x-0.5 md:size-6" />
                        </Link>

                        <a href="/" class="group inline-flex shrink-0 items-center gap-2.5 text-xl font-black text-primary transition-all hover:scale-[1.02] md:text-2xl">
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary text-white shadow-md shadow-primary/20 transition-shadow group-hover:shadow-lg group-hover:shadow-primary/30">
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
                            <div class="h-full rounded-full bg-gradient-to-r from-warning to-primary shadow-sm shadow-primary/30 transition-all duration-500" :style="{ width: `${progress}%` }" />
                        </div>
                        <div v-else class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100 shadow-inner">
                            <div class="h-full rounded-full bg-gradient-to-r from-warning to-primary shadow-sm shadow-primary/30 transition-all duration-500" :style="{ width: `${progress}%` }" />
                        </div>
                        <AsrVisualizationToggle />
                        <SyncStatusBadge />
                    </div>
                </header>
                <main v-if="assessmentTask" class="learner-frame min-h-0 flex-1 pb-0 pt-2 sm:py-2">
                    <slot />
                </main>
                <main v-else class="learner-frame learner-stage" :class="!hasBottomBar ? '!pb-2 lg:!pb-4' : ''">
                    <div v-if="$slots.agent" class="learner-stage-grid">
                        <aside class="learner-stage-sidebar flex flex-col gap-4 pb-4 lg:sticky lg:top-20 lg:z-10 lg:px-2 lg:-mx-2 lg:pb-8">
                            <slot name="agent" />
                            <div id="teleport-audio-review" class="learner-stage-review empty:hidden"></div>
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
        </div>
    </div>
</template>

<style scoped>
.anim-header {
    animation: headerFade 0.4s ease-out forwards;
}

.rd-learner-assessment-header {
    border: 2px solid var(--rd-frame-border);
    border-radius: 26px;
    background: var(--rd-story-surface);
    padding: 8px 14px 12px;
    box-shadow: 0 6px 0 var(--rd-lip), 0 8px 0 var(--rd-lip-dark), 0 22px 30px -12px var(--rd-shadow);
}

.rd-learner-assessment-header-face {
    min-width: 0;
    border: 1.5px solid var(--rd-face-border);
    border-radius: 18px;
    background: var(--rd-face-surface);
    box-shadow: inset 0 2px 0 var(--rd-highlight), inset 0 -6px 10px var(--rd-inner-shade);
}

.rd-learner-header-icon {
    border: 2px solid var(--rd-story-border-soft);
    background: var(--rd-story-surface);
    color: var(--rd-text-main);
    box-shadow: 0 4px 0 rgba(111, 101, 52, 0.16), 0 8px 14px rgba(54, 83, 101, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.rd-learner-header-icon:hover {
    color: var(--rd-primary-orange);
}
@keyframes headerFade {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 1023px) {
    .learner-stage-sidebar {
        display: contents;
    }
    .learner-content {
        order: 1;
    }
    .learner-stage-review {
        order: 2;
    }
}
</style>
