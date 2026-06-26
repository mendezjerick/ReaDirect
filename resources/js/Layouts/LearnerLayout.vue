
<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import SyncStatusBadge from '../Components/SyncStatusBadge.vue';
import AdminTestingToolbar from '../Components/Admin/AdminTestingToolbar.vue';
import AsrVisualizationToggle from '../Components/AsrVisualizationToggle.vue';

const props = defineProps({
    progress: { type: Number, default: 0 },
    steps: { type: Array, default: () => [] },
    diagnosticStep: { type: String, default: '' },
    backUrl: { type: String, default: '' },
    backLabel: { type: String, default: 'Back' },
    assessmentTask: { type: Boolean, default: false },
});
import { ref, onErrorCaptured } from 'vue';

const errorDetails = ref(null);
onErrorCaptured((err, instance, info) => {
    errorDetails.value = { err: err.toString(), info };
    return false; // prevent propagation
});
</script>

<template>
    <div
        class="text-text relative"
        :class="assessmentTask ? 'flex h-screen flex-col overflow-hidden bg-[#EBF5FF]' : 'min-h-screen bg-[#EBF5FF]'"
    >
        <div v-if="errorDetails" class="absolute inset-0 z-50 bg-red-100 p-8 text-red-900 overflow-auto">
            <h2 class="text-2xl font-bold mb-4">Vue Runtime Error!</h2>
            <pre class="bg-white p-4 rounded text-sm font-mono whitespace-pre-wrap">{{ errorDetails.err }}</pre>
            <p class="mt-4 font-bold">Info: {{ errorDetails.info }}</p>
        </div>
        <AdminTestingToolbar />
        <header
            v-if="assessmentTask"
            class="anim-header z-20 flex-none py-3"
        >
            <div class="learner-frame flex items-center justify-between px-2">
                <!-- Help button -->
                <button
                    class="grid size-12 place-items-center rounded-full bg-[#DBEAFE] text-[#2563EB] font-bold text-2xl border-[3px] border-white shadow-md transition-transform active:scale-95 hover:bg-[#BFDBFE]"
                    title="Help"
                    aria-label="Help"
                >
                    ?
                </button>

                <!-- ReaDirect title -->
                <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-[#1E3A8A]" style="font-family: 'Fredoka', system-ui, sans-serif;">
                    ReaDirect
                </h1>

                <!-- Right Side Actions -->
                <div class="flex items-center gap-3">
                    <AsrVisualizationToggle />

                    <!-- Settings button -->
                    <button
                        class="grid size-12 place-items-center rounded-full bg-[#DBEAFE] text-[#2563EB] border-[3px] border-white shadow-md transition-transform active:scale-95 hover:bg-[#BFDBFE]"
                        title="Settings"
                        aria-label="Settings"
                    >
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </header>
        <header v-else class="anim-header relative z-20 py-3">
            <div class="learner-frame flex items-center justify-between px-2">
                <!-- Left: back button or help button -->
                <Link
                    v-if="backUrl"
                    :href="backUrl"
                    class="grid size-12 place-items-center rounded-full bg-[#DBEAFE] text-[#2563EB] border-[3px] border-white shadow-md transition-transform active:scale-95 hover:bg-[#BFDBFE]"
                    :title="backLabel"
                    :aria-label="backLabel"
                >
                    <ArrowLeft class="size-5 stroke-[2.5]" />
                </Link>
                <div v-else class="size-12" />

                <!-- Center: title -->
                <div class="flex flex-1 flex-col items-center gap-1.5 px-3">
                    <h1 class="text-2xl font-semibold tracking-tight text-[#1E3A8A] md:text-3xl" style="font-family: 'Fredoka', system-ui, sans-serif;">
                        ReaDirect
                    </h1>
                </div>

                <!-- Right: ASR toggle + settings -->
                <div class="flex items-center gap-3">
                    <AsrVisualizationToggle />
                    <SyncStatusBadge />
                </div>
            </div>
        </header>
        <main v-if="assessmentTask" class="learner-frame min-h-0 flex-1 overflow-hidden py-2">
            <slot />
        </main>
        <main v-else class="learner-frame learner-stage">
            <div v-if="$slots.agent" class="learner-stage-grid">
                <aside class="learner-stage-sidebar flex flex-col gap-4 pb-4 lg:sticky lg:top-20 lg:z-10 lg:max-h-[calc(100vh-140px)] lg:overflow-y-auto lg:px-2 lg:-mx-2 lg:pb-8">
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
</template>

<style scoped>
.anim-header {
    animation: headerFade 0.4s ease-out forwards;
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
