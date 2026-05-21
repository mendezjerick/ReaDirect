<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    ArrowRight,
    Bot,
    Eye,
    Filter,
    Loader2,
    RotateCcw,
    Search,
    Zap,
} from 'lucide-vue-next';

defineProps({ agents: Array, filters: Object, filterOptions: Object });

const filtering = ref(false);

const filter = (event) => {
    filtering.value = true;
    router.get('/admin/agents', Object.fromEntries(new FormData(event.target).entries()), {
        preserveState: true,
        onFinish: () => { filtering.value = false; },
    });
};
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-text">Agents</h1>
            <p class="mt-1 text-sm font-medium text-muted">Manage AI agent profiles, types, and LLM configurations.</p>
        </div>

        <!-- ── Filters card ────────────────────────────────── -->
        <DashboardCard class="ag-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                    <Filter class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Filter Agents</h2>
            </div>

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="filter">
                <label class="grid gap-1.5 sm:col-span-2 lg:col-span-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative">
                        <Search class="absolute left-3 top-3 size-4 text-muted" />
                        <input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search agents">
                    </div>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Agent type</span>
                    <select name="agent_type" :value="filters.agent_type" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">All agent types</option>
                        <option v-for="type in filterOptions.agentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Status</span>
                    <select name="status" :value="filters.status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="filtering">
                        <Loader2 v-if="filtering" class="size-4 animate-spin" />
                        <Filter v-else class="size-4" />
                        Filter
                    </button>
                    <Link href="/admin/agents" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                        <RotateCcw class="size-4" />
                        Reset
                    </Link>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Agents table ────────────────────────────────── -->
        <DashboardCard class="mt-5 ag-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <Bot class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Agent Profiles</h2>
                </div>
                <StatusBadge v-if="agents.length" :status="`${agents.length} agents`" />
            </div>

            <EmptyState v-if="agents.length === 0" title="No agents found" message="No agents match the selected filters." />

            <!-- Desktop table -->
            <div v-else class="hidden sm:block overflow-hidden rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Agent</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Type</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-center">LLM</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Status</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(agent, index) in agents" :key="agent.id" class="group transition-colors duration-150 hover:bg-primary-light/40 ag-row-in" :style="{ '--row-delay': `${index * 40}ms` }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-xs font-bold">
                                        {{ (agent.name ?? '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="font-semibold text-text group-hover:text-primary transition-colors">{{ agent.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">{{ agent.agent_type }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold" :class="agent.uses_llm ? 'bg-green-50 text-green-600' : 'bg-slate-100 text-slate-500'">
                                    <Zap v-if="agent.uses_llm" class="size-3" />
                                    {{ agent.uses_llm ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="agent.is_active ? 'Active' : 'Inactive'" :variant="agent.is_active ? 'success' : 'danger'" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/admin/agents/${agent.id}`" class="group/link inline-flex items-center gap-1.5 text-[13px] font-bold text-primary transition-colors hover:text-primary-dark">
                                    <Eye class="size-3.5" />
                                    View
                                    <ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card list -->
            <div v-if="agents.length > 0" class="sm:hidden divide-y divide-border/60">
                <Link v-for="(agent, index) in agents" :key="'m-' + agent.id" :href="`/admin/agents/${agent.id}`" class="group flex items-center justify-between gap-3 py-3.5 first:pt-0 last:pb-0 ag-row-in" :style="{ '--row-delay': `${index * 40}ms` }">
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-sm font-bold">
                            {{ (agent.name ?? '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-text truncate group-hover:text-primary transition-colors">{{ agent.name }}</p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium">{{ agent.agent_type }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <StatusBadge :status="agent.is_active ? 'Active' : 'Inactive'" :variant="agent.is_active ? 'success' : 'danger'" />
                        <ArrowRight class="size-4 text-slate-300 group-hover:text-primary group-hover:translate-x-0.5 transition-all duration-200" />
                    </div>
                </Link>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.ag-card-in { animation: ag-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes ag-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.ag-row-in { animation: ag-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes ag-row { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: translateX(0); } }
</style>
