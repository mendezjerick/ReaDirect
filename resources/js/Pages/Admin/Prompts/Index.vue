<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    ArrowRight,
    Clock,
    Eye,
    FileText,
    Filter,
    Loader2,
    MessageSquare,
    Plus,
    RotateCcw,
    Search,
} from 'lucide-vue-next';

defineProps({ prompts: Object, filters: Object, filterOptions: Object });

const filtering = ref(false);

const filter = (event) => {
    filtering.value = true;
    router.get('/admin/prompts', Object.fromEntries(new FormData(event.target).entries()), {
        preserveState: true,
        onFinish: () => { filtering.value = false; },
    });
};

const statusVariant = (status) => {
    if (!status) return 'primary';
    const s = String(status).toLowerCase();
    if (s === 'active') return 'success';
    if (s === 'inactive' || s === 'archived') return 'danger';
    if (s === 'draft') return 'warning';
    return 'primary';
};
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Prompt Templates</h1>
                <p class="mt-1 text-sm font-medium text-muted">{{ prompts.total }} records found</p>
            </div>
            <div class="flex gap-2">
                <Link href="/admin/prompts/history" class="group inline-flex items-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                    <Clock class="size-4" />
                    History
                </Link>
                <Link href="/admin/prompts/create" class="group inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                    <Plus class="size-4" />
                    Create
                </Link>
            </div>
        </div>

        <!-- ── Filters card ────────────────────────────────── -->
        <DashboardCard class="pt-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                    <Filter class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Filter Prompts</h2>
            </div>

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="filter">
                <!-- Search -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative">
                        <Search class="absolute left-3 top-3 size-4 text-muted" />
                        <input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search prompts">
                    </div>
                </label>

                <!-- Prompt type -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Prompt type</span>
                    <select name="prompt_type" :value="filters.prompt_type" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">All prompt types</option>
                        <option v-for="type in filterOptions.promptTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>

                <!-- Agent -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Agent</span>
                    <select name="agent_type" :value="filters.agent_type" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">All agents</option>
                        <option v-for="type in filterOptions.agentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>

                <!-- Status -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Status</span>
                    <select name="status" :value="filters.status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </label>

                <!-- Actions -->
                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="filtering"
                    >
                        <Loader2 v-if="filtering" class="size-4 animate-spin" />
                        <Filter v-else class="size-4" />
                        Filter
                    </button>
                    <Link
                        href="/admin/prompts"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]"
                    >
                        <RotateCcw class="size-4" />
                        Reset
                    </Link>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Prompts table ───────────────────────────────── -->
        <DashboardCard class="mt-5 pt-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <FileText class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Prompt Templates</h2>
                </div>
                <StatusBadge v-if="prompts.data.length" :status="`${prompts.total} total`" />
            </div>

            <!-- Empty state -->
            <EmptyState
                v-if="prompts.data.length === 0"
                title="No prompts found"
                message="No prompt templates match the selected filters. Try adjusting or resetting your filters."
            />

            <!-- Desktop table (hidden on mobile) -->
            <div v-else class="hidden sm:block overflow-hidden rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Key</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Agent</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-center">Version</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Status</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(prompt, index) in prompts.data"
                            :key="prompt.id"
                            class="group transition-colors duration-150 hover:bg-primary-light/40 pt-row-in"
                            :style="{ '--row-delay': `${index * 35}ms` }"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                                        <MessageSquare class="size-3.5" />
                                    </div>
                                    <span class="font-semibold text-text group-hover:text-primary transition-colors">{{ prompt.key }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted font-medium">{{ prompt.agent_profile?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-600">
                                    v{{ prompt.version }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge :status="prompt.status" :variant="statusVariant(prompt.status)" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/admin/prompts/${prompt.id}`"
                                    class="group/link inline-flex items-center gap-1.5 text-[13px] font-bold text-primary transition-colors hover:text-primary-dark"
                                >
                                    <Eye class="size-3.5" />
                                    View
                                    <ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card list (shown on small screens) -->
            <div v-if="prompts.data.length > 0" class="sm:hidden divide-y divide-border/60">
                <Link
                    v-for="(prompt, index) in prompts.data"
                    :key="'m-' + prompt.id"
                    :href="`/admin/prompts/${prompt.id}`"
                    class="group flex items-center justify-between gap-3 py-3.5 first:pt-0 last:pb-0 pt-row-in"
                    :style="{ '--row-delay': `${index * 35}ms` }"
                >
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                            <MessageSquare class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-text truncate group-hover:text-primary transition-colors">{{ prompt.key }}</p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium">
                                {{ prompt.agent_profile?.name ?? '—' }} · v{{ prompt.version }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <StatusBadge :status="prompt.status" :variant="statusVariant(prompt.status)" />
                        <ArrowRight class="size-4 text-slate-300 transition-all duration-200 group-hover:text-primary group-hover:translate-x-0.5" />
                    </div>
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="prompts.links && prompts.last_page > 1" class="mt-5 flex items-center justify-center gap-1.5 border-t border-border/60 pt-4">
                <Link
                    v-for="link in prompts.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="inline-flex min-w-[36px] items-center justify-center rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-all duration-200"
                    :class="link.active
                        ? 'bg-primary text-white shadow-sm shadow-primary/20'
                        : link.url
                            ? 'text-slate-500 hover:bg-primary-light hover:text-primary'
                            : 'text-slate-300 cursor-not-allowed'"
                    v-html="link.label"
                />
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
/* ─── Staggered card entrance ─────────────────────────── */
.pt-card-in {
    animation: pt-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}

@keyframes pt-card-entrance {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ─── Staggered row entrance ──────────────────────────── */
.pt-row-in {
    animation: pt-row-entrance 350ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--row-delay, 0ms);
}

@keyframes pt-row-entrance {
    from {
        opacity: 0;
        transform: translateX(-6px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>
