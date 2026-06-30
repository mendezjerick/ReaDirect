<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { BookOpen, LogOut, Map } from 'lucide-vue-next';

const page = usePage();
const storageKey = 'readirect_qa_viewport_mode';
const viewportModes = [
    { value: 'auto', label: 'Auto' },
    { value: 'desktop', label: 'Desktop' },
    { value: 'tablet', label: 'Tablet' },
    { value: 'mobile-vertical', label: 'Mobile Vertical' },
    { value: 'mobile-horizontal', label: 'Mobile Horizontal' },
];
const validModeValues = viewportModes.map((mode) => mode.value);

const getStoredMode = () => {
    if (typeof window === 'undefined') return null;

    try {
        return window.localStorage.getItem(storageKey);
    } catch {
        return null;
    }
};

const storeMode = (mode) => {
    if (typeof window === 'undefined') return;

    try {
        window.localStorage.setItem(storageKey, mode);
    } catch {
        // Keep the QA control usable even if browser storage is unavailable.
    }
};

const readStoredMode = () => {
    const stored = getStoredMode();

    if (validModeValues.includes(stored)) {
        return stored;
    }

    if (stored !== null) {
        storeMode('auto');
    }

    return 'auto';
};

const pageShell = ref(null);
const selectedViewportMode = ref(readStoredMode());
const isTestingMode = computed(() => page.props.adminTesting?.enabled === true);
const selectedMode = computed(() => viewportModes.find((mode) => mode.value === selectedViewportMode.value) ?? viewportModes[0]);
const isForcedViewport = computed(() => selectedViewportMode.value !== 'auto');

let resizeObserver = null;

const notifyViewportMode = (mode) => {
    if (typeof window === 'undefined') return;

    window.dispatchEvent(new CustomEvent('readirect:qa-viewport-change', {
        detail: { mode },
    }));
};

const applyViewportMode = () => {
    if (typeof document === 'undefined') return;

    if (!isTestingMode.value) {
        delete document.body.dataset.qaViewport;
        document.body.style.removeProperty('--qa-debug-bar-height');
        notifyViewportMode('auto');
        return;
    }

    const mode = validModeValues.includes(selectedViewportMode.value)
        ? selectedViewportMode.value
        : 'auto';

    selectedViewportMode.value = mode;
    document.body.dataset.qaViewport = mode;
    document.body.style.setProperty('--qa-debug-bar-height', `${pageShell.value?.offsetHeight || 36}px`);
    storeMode(mode);
    notifyViewportMode(mode);
};

onMounted(() => {
    applyViewportMode();

    if (typeof ResizeObserver !== 'undefined' && pageShell.value) {
        resizeObserver = new ResizeObserver(applyViewportMode);
        resizeObserver.observe(pageShell.value);
    }
});

onUnmounted(() => {
    resizeObserver?.disconnect();

    if (typeof document !== 'undefined') {
        delete document.body.dataset.qaViewport;
        document.body.style.removeProperty('--qa-debug-bar-height');
    }
});

watch([isTestingMode, selectedViewportMode], applyViewportMode);
</script>

<template>
    <div v-if="isTestingMode" ref="pageShell" class="rd-admin-testing-shell relative z-50 flex-none px-4 text-xs text-text">
        <div class="rd-admin-testing-bar learner-frame flex min-h-8 items-center justify-between gap-3 px-3 py-1">
            <div class="inline-flex shrink-0 items-center gap-2 font-black text-primary">
                <BookOpen class="size-4" />
                Testing Mode
            </div>
            <div class="flex min-w-0 flex-wrap items-center justify-center gap-x-3 gap-y-0.5 font-semibold">
                <span>Learner ID: {{ page.props.adminTesting.learner_id ?? 'not selected' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox assessment: {{ page.props.adminTesting.assessment_attempt_id ?? '-' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox module: {{ page.props.adminTesting.module_attempt_id ?? '-' }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <label class="rd-admin-testing-viewport">
                    <span>Viewport:</span>
                    <select v-model="selectedViewportMode" aria-label="QA viewport preview mode">
                        <option
                            v-for="mode in viewportModes"
                            :key="mode.value"
                            :value="mode.value"
                        >
                            {{ mode.label }}
                        </option>
                    </select>
                </label>
                <span v-if="isForcedViewport" class="rd-admin-testing-mode-badge">
                    {{ selectedMode.label }}
                </span>
                <a class="rd-admin-testing-button" href="/admin/testing/flow-jump">
                    <Map class="size-3.5" />
                    Jump menu
                </a>
                <form method="post" action="/admin/testing/exit">
                    <input type="hidden" name="_token" :value="page.props.csrf_token">
                    <button class="rd-admin-testing-button rd-admin-testing-button--exit">
                        <LogOut class="size-3.5" />
                        Exit
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rd-admin-testing-shell {
    background: rgba(255, 253, 248, 0.9);
    border-bottom: 1px solid rgba(224, 207, 166, 0.55);
}

.rd-admin-testing-bar {
    color: var(--rd-text-main);
}

.rd-admin-testing-button {
    display: inline-flex;
    min-height: 1.55rem;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: 1px solid rgba(224, 207, 166, 0.75);
    border-radius: 999px;
    background: rgba(255, 253, 248, 0.88);
    padding: 0.18rem 0.7rem;
    color: var(--rd-text-main);
    font-weight: 900;
    box-shadow: none;
}

.rd-admin-testing-button--exit {
    border-color: #D9652F;
    background: #F58549;
    color: white;
}

.rd-admin-testing-viewport {
    display: inline-flex;
    min-height: 1.55rem;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid rgba(224, 207, 166, 0.75);
    border-radius: 999px;
    background: rgba(255, 253, 248, 0.88);
    padding: 0.12rem 0.35rem 0.12rem 0.65rem;
    color: var(--rd-text-main);
    font-weight: 900;
    white-space: nowrap;
}

.rd-admin-testing-viewport select {
    height: 1.25rem;
    min-height: 0;
    border: 0;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.12);
    padding: 0 1.45rem 0 0.45rem;
    color: var(--rd-text-main);
    font-size: 0.75rem;
    font-weight: 900;
    line-height: 1;
    box-shadow: none;
}

.rd-admin-testing-viewport select:focus {
    box-shadow: 0 0 0 2px rgba(245, 133, 73, 0.24);
}

.rd-admin-testing-mode-badge {
    display: inline-flex;
    min-height: 1.55rem;
    align-items: center;
    border-radius: 999px;
    background: rgba(245, 133, 73, 0.16);
    padding: 0.18rem 0.6rem;
    color: var(--rd-primary-orange-dark);
    font-weight: 900;
    white-space: nowrap;
}
</style>
