<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import {
    ArrowLeft,
    ChevronDown,
    Code2,
    FileText,
    MessageSquare,
    Pencil,
} from 'lucide-vue-next';

defineProps({ prompt: Object });

const showVariables = ref(false);

const statusVariant = (status) => {
    const s = String(status ?? '').toLowerCase();
    if (s === 'active') return 'success';
    if (s === 'inactive' || s === 'archived') return 'danger';
    if (s === 'draft') return 'warning';
    return 'primary';
};

const detailRows = (prompt) => [
    { label: 'Key', value: prompt.key },
    { label: 'Agent', value: prompt.agent_profile?.name ?? '—' },
    { label: 'Version', value: `v${prompt.version}`, isBadge: true },
    { label: 'Status', value: prompt.status, isStatus: true },
    { label: 'Created', value: prompt.created_at ?? '—' },
    { label: 'Updated', value: prompt.updated_at ?? '—' },
];
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">{{ prompt.key }}</h1>
                <div class="mt-1.5 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-600">v{{ prompt.version }}</span>
                    <StatusBadge :status="prompt.status" :variant="statusVariant(prompt.status)" />
                </div>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/prompts" class="group inline-flex items-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                    <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                    Back
                </Link>
                <Link :href="`/admin/prompts/${prompt.id}/edit`" class="group inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                    <Pencil class="size-4" />
                    Edit
                </Link>
            </div>
        </div>

        <!-- ── Details card ────────────────────────────────── -->
        <DashboardCard class="ps-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                    <MessageSquare class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Prompt Details</h2>
            </div>

            <div class="space-y-1">
                <div
                    v-for="row in detailRows(prompt)"
                    :key="row.label"
                    class="flex items-center justify-between gap-3 rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors duration-150 hover:bg-slate-100"
                >
                    <span class="font-semibold text-muted">{{ row.label }}</span>
                    <StatusBadge v-if="row.isStatus" :status="row.value" :variant="statusVariant(row.value)" />
                    <span v-else-if="row.isBadge" class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-600">{{ row.value }}</span>
                    <span v-else class="font-bold text-text text-right truncate max-w-[60%]">{{ row.value }}</span>
                </div>
            </div>
        </DashboardCard>

        <!-- ── Template content ────────────────────────────── -->
        <DashboardCard class="mt-5 ps-card-in" style="--card-delay: 80ms">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                    <FileText class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Template Content</h2>
            </div>
            <div class="overflow-hidden rounded-xl border border-border/60">
                <pre class="max-h-[500px] overflow-auto bg-slate-950 p-4 text-xs font-mono leading-relaxed text-slate-100 whitespace-pre-wrap break-words">{{ prompt.template ?? 'No template content' }}</pre>
            </div>
        </DashboardCard>

        <!-- ── Variables JSON ──────────────────────────────── -->
        <DashboardCard v-if="prompt.variables" class="mt-5 ps-card-in" style="--card-delay: 160ms">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                        <Code2 class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Variables</h2>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-border/60 bg-background px-3 py-1.5 text-[12px] font-semibold text-slate-500 transition-all duration-200 hover:bg-slate-100 hover:text-text active:scale-[0.97]"
                    @click="showVariables = !showVariables"
                >
                    <ChevronDown class="size-3.5 transition-transform duration-200" :class="showVariables ? 'rotate-180' : ''" />
                    {{ showVariables ? 'Hide' : 'Show' }} JSON
                </button>
            </div>

            <Transition name="ps-expand">
                <div v-if="showVariables" class="mt-3 overflow-hidden rounded-xl border border-border/60">
                    <pre class="max-h-64 overflow-auto bg-slate-950 p-4 text-xs font-mono leading-relaxed text-slate-100 whitespace-pre-wrap break-words">{{ typeof prompt.variables === 'string' ? prompt.variables : JSON.stringify(prompt.variables, null, 2) }}</pre>
                </div>
            </Transition>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.ps-card-in {
    animation: ps-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}
@keyframes ps-card-entrance {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.ps-expand-enter-active { transition: all 300ms cubic-bezier(0.16, 1, 0.3, 1); }
.ps-expand-leave-active { transition: all 200ms ease; }
.ps-expand-enter-from { opacity: 0; transform: translateY(-6px); }
.ps-expand-leave-to   { opacity: 0; transform: translateY(-4px); }
</style>
