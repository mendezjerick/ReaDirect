<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    ArrowLeft,
    ArrowRight,
    Bot,
    FileText,
    MessageSquare,
    Pencil,
    Zap,
} from 'lucide-vue-next';

defineProps({ agent: Object, prompts: Array });

const statusVariant = (s) => {
    const v = String(s ?? '').toLowerCase();
    if (v === 'active') return 'success';
    if (v === 'inactive' || v === 'archived') return 'danger';
    if (v === 'draft') return 'warning';
    return 'primary';
};

const detailRows = (agent) => [
    { label: 'Name', value: agent.name },
    { label: 'Type', value: agent.agent_type, isPill: true },
    { label: 'Purpose', value: agent.purpose ?? '—' },
    { label: 'Uses LLM', value: agent.uses_llm, isBool: true },
    { label: 'Status', value: agent.is_active ? 'Active' : 'Inactive', isStatus: true, variant: agent.is_active ? 'success' : 'danger' },
    { label: 'Default State', value: agent.default_state ?? '—' },
    { label: 'Sprite', value: agent.sprite_path ?? '—' },
];
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">{{ agent.name }}</h1>
                <p class="mt-1 text-sm font-medium text-muted">{{ agent.purpose ?? 'Agent profile details' }}</p>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/agents" class="group inline-flex items-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                    <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                    Back
                </Link>
                <Link :href="`/admin/agents/${agent.id}/edit`" class="group inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                    <Pencil class="size-4" />
                    Edit
                </Link>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <!-- ── Agent details ───────────────────────────── -->
            <DashboardCard class="as-card-in">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <Bot class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Agent Details</h2>
                </div>

                <div class="space-y-1">
                    <div v-for="row in detailRows(agent)" :key="row.label" class="flex items-center justify-between gap-3 rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors duration-150 hover:bg-slate-100">
                        <span class="font-semibold text-muted">{{ row.label }}</span>
                        <StatusBadge v-if="row.isStatus" :status="row.value" :variant="row.variant" />
                        <span v-else-if="row.isPill" class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">{{ row.value }}</span>
                        <span v-else-if="row.isBool" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="row.value ? 'bg-green-50 text-green-600' : 'bg-slate-100 text-slate-500'">
                            <Zap v-if="row.value" class="size-3" />
                            {{ row.value ? 'Yes' : 'No' }}
                        </span>
                        <span v-else class="font-bold text-text text-right truncate max-w-[60%]">{{ row.value }}</span>
                    </div>
                </div>
            </DashboardCard>

            <!-- ── Prompt Templates ────────────────────────── -->
            <DashboardCard class="as-card-in" style="--card-delay: 80ms">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                            <FileText class="size-4" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Prompt Templates</h2>
                    </div>
                    <StatusBadge v-if="prompts?.length" :status="`${prompts.length} prompts`" />
                </div>

                <EmptyState v-if="!prompts || prompts.length === 0" title="No prompts" message="No prompt templates linked to this agent." />

                <div v-else class="divide-y divide-border/60">
                    <Link v-for="(prompt, index) in prompts" :key="prompt.id" :href="`/admin/prompts/${prompt.id}`" class="group flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 as-row-in" :style="{ '--row-delay': `${index * 40}ms` }">
                        <div class="min-w-0 flex items-center gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                                <MessageSquare class="size-3.5" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-text truncate group-hover:text-primary transition-colors">{{ prompt.key }}</p>
                                <p class="text-[11px] text-muted font-medium">v{{ prompt.version }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <StatusBadge :status="prompt.status" :variant="statusVariant(prompt.status)" />
                            <ArrowRight class="size-3.5 text-slate-300 transition-all duration-200 group-hover:text-primary group-hover:translate-x-0.5" />
                        </div>
                    </Link>
                </div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.as-card-in { animation: as-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes as-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.as-row-in { animation: as-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes as-row { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: translateX(0); } }
</style>
