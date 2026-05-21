<script setup>
import { computed, ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import {
    Activity,
    ChevronDown,
    Cloud,
    Cpu,
    Database,
    HardDrive,
    HeartPulse,
    Layers,
    MessageSquare,
    Server,
    Zap,
} from 'lucide-vue-next';

const props = defineProps({ system: Object });

/* Map section keys to icons and colors for visual variety */
const sectionMeta = {
    database:  { icon: Database,       color: 'blue',    bg: 'bg-blue-50',    text: 'text-blue-500' },
    queue:     { icon: Layers,         color: 'violet',  bg: 'bg-violet-50',  text: 'text-violet-500' },
    storage:   { icon: HardDrive,      color: 'emerald', bg: 'bg-emerald-50', text: 'text-emerald-500' },
    stt:       { icon: MessageSquare,  color: 'sky',     bg: 'bg-sky-50',     text: 'text-sky-500' },
    llm:       { icon: Zap,            color: 'amber',   bg: 'bg-amber-50',   text: 'text-amber-500' },
    runtime:   { icon: Cpu,            color: 'orange',  bg: 'bg-orange-50',  text: 'text-orange-500' },
    cache:     { icon: Server,         color: 'rose',    bg: 'bg-rose-50',    text: 'text-rose-500' },
    redis:     { icon: Server,         color: 'red',     bg: 'bg-red-50',     text: 'text-red-500' },
    services:  { icon: Cloud,          color: 'indigo',  bg: 'bg-indigo-50',  text: 'text-indigo-500' },
};
const fallbackMeta = { icon: Activity, color: 'slate', bg: 'bg-slate-50', text: 'text-slate-500' };

const getMeta = (key) => sectionMeta[key.toLowerCase()] ?? fallbackMeta;

/* Parse status from a section's data */
const getStatus = (section) => {
    if (!section || typeof section !== 'object') return null;
    const s = String(section.status ?? section.state ?? '').toLowerCase();
    if (!s) return null;
    return s;
};

const getStatusVariant = (status) => {
    if (!status) return 'primary';
    if (['ok', 'running', 'active', 'connected', 'healthy', 'up'].includes(status)) return 'success';
    if (['error', 'down', 'failed', 'disconnected', 'unhealthy'].includes(status)) return 'danger';
    if (['warning', 'degraded', 'slow'].includes(status)) return 'warning';
    return 'primary';
};

/* Track which JSON viewers are expanded */
const expanded = ref({});
const toggleRaw = (key) => { expanded.value[key] = !expanded.value[key]; };

/* Flatten section object into key-value pairs for the detail table */
const flattenSection = (section) => {
    if (!section || typeof section !== 'object') return [];
    return Object.entries(section).map(([key, value]) => ({
        key,
        label: key.replace(/_/g, ' '),
        value: value === null || value === undefined ? 'null'
             : typeof value === 'boolean' ? (value ? 'true' : 'false')
             : typeof value === 'object' ? JSON.stringify(value)
             : String(value),
        isStatus: key === 'status' || key === 'state',
        isBool: typeof value === 'boolean',
        boolValue: value === true,
    }));
};

/* Overall system health summary */
const healthSummary = computed(() => {
    if (!props.system) return { ok: 0, warning: 0, error: 0, total: 0 };
    let ok = 0, warning = 0, error = 0;
    for (const section of Object.values(props.system)) {
        const s = getStatus(section);
        const v = getStatusVariant(s);
        if (v === 'success') ok++;
        else if (v === 'danger') error++;
        else if (v === 'warning') warning++;
        else ok++;
    }
    return { ok, warning, error, total: ok + warning + error };
});
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-text">System Monitoring</h1>
            <p class="mt-1 text-sm font-medium text-muted">Live overview of database, queues, storage, AI services, and runtime environment.</p>
        </div>

        <!-- ── Health summary strip ────────────────────────── -->
        <div class="mb-6 grid gap-3 sm:grid-cols-3 sm-card-in">
            <div class="flex items-center gap-3 rounded-2xl border border-green-200/60 bg-green-50/50 px-4 py-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <HeartPulse class="size-4" />
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-green-600">Healthy</p>
                    <p class="text-xl font-extrabold text-green-700">{{ healthSummary.ok }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl border border-amber-200/60 bg-amber-50/50 px-4 py-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                    <Activity class="size-4" />
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-amber-600">Warning</p>
                    <p class="text-xl font-extrabold text-amber-700">{{ healthSummary.warning }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl border border-red-200/60 bg-red-50/50 px-4 py-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <Zap class="size-4" />
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-red-600">Error</p>
                    <p class="text-xl font-extrabold text-red-700">{{ healthSummary.error }}</p>
                </div>
            </div>
        </div>

        <!-- ── Section cards ───────────────────────────────── -->
        <div class="grid gap-4 lg:grid-cols-2">
            <DashboardCard
                v-for="(section, key, index) in system"
                :key="key"
                class="sm-card-in"
                :style="{ '--card-delay': `${(index + 1) * 60}ms` }"
            >
                <!-- Card header -->
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="[getMeta(key).bg, getMeta(key).text]">
                            <component :is="getMeta(key).icon" class="size-4" />
                        </div>
                        <h2 class="text-sm font-bold text-text capitalize">{{ key }}</h2>
                    </div>
                    <StatusBadge
                        v-if="getStatus(section)"
                        :status="getStatus(section)"
                        :variant="getStatusVariant(getStatus(section))"
                    />
                </div>

                <!-- Key-value pairs -->
                <div class="space-y-1">
                    <div
                        v-for="entry in flattenSection(section)"
                        :key="entry.key"
                        class="flex items-center justify-between gap-3 rounded-xl bg-background px-3.5 py-2 text-sm transition-colors duration-150 hover:bg-slate-100"
                    >
                        <span class="font-semibold text-muted capitalize truncate">{{ entry.label }}</span>
                        <span v-if="entry.isStatus" class="shrink-0">
                            <StatusBadge :status="entry.value" :variant="getStatusVariant(entry.value)" />
                        </span>
                        <span v-else-if="entry.isBool" class="shrink-0">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold"
                                  :class="entry.boolValue ? 'bg-green-50 text-green-600' : 'bg-slate-100 text-slate-500'">
                                {{ entry.boolValue ? 'Yes' : 'No' }}
                            </span>
                        </span>
                        <span v-else class="font-bold text-text text-right truncate max-w-[60%]" :title="entry.value">{{ entry.value }}</span>
                    </div>
                </div>

                <!-- Raw JSON toggle -->
                <button
                    type="button"
                    class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-border/60 bg-background px-3 py-2 text-[12px] font-semibold text-slate-500 transition-all duration-200 hover:bg-slate-100 hover:text-text active:scale-[0.98]"
                    @click="toggleRaw(key)"
                >
                    <ChevronDown class="size-3.5 transition-transform duration-200" :class="expanded[key] ? 'rotate-180' : ''" />
                    {{ expanded[key] ? 'Hide' : 'Show' }} raw JSON
                </button>

                <Transition name="sm-expand">
                    <div v-if="expanded[key]" class="mt-2 overflow-hidden rounded-xl border border-border/60">
                        <pre class="max-h-64 overflow-auto bg-slate-950 p-4 text-xs font-mono leading-relaxed text-slate-100 whitespace-pre-wrap break-words">{{ JSON.stringify(section, null, 2) }}</pre>
                    </div>
                </Transition>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* ─── Staggered card entrance ─────────────────────────── */
.sm-card-in {
    animation: sm-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}

@keyframes sm-card-entrance {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ─── JSON expand/collapse transition ─────────────────── */
.sm-expand-enter-active {
    transition: all 300ms cubic-bezier(0.16, 1, 0.3, 1);
}
.sm-expand-leave-active {
    transition: all 200ms ease;
}
.sm-expand-enter-from {
    opacity: 0;
    transform: translateY(-6px);
    max-height: 0;
}
.sm-expand-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
