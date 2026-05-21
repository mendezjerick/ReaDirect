<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    ArrowLeft,
    ArrowRight,
    Clock,
    MessageSquare,
} from 'lucide-vue-next';

defineProps({ prompts: Array });

const statusVariant = (status) => {
    const s = String(status ?? '').toLowerCase();
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
                <h1 class="text-2xl font-extrabold text-text">Prompt History</h1>
                <p class="mt-1 text-sm font-medium text-muted">Version history and audit trail for all prompt templates.</p>
            </div>
            <Link href="/admin/prompts" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to Prompts
            </Link>
        </div>

        <!-- ── History list ────────────────────────────────── -->
        <DashboardCard class="ph-card-in">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                        <Clock class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">All Versions</h2>
                </div>
                <StatusBadge v-if="prompts.length" :status="`${prompts.length} entries`" />
            </div>

            <EmptyState
                v-if="!prompts || prompts.length === 0"
                title="No history found"
                message="No prompt version history is available yet."
            />

            <div v-else class="divide-y divide-border/60">
                <div
                    v-for="(entry, index) in prompts"
                    :key="entry.id ?? index"
                    class="group flex flex-col gap-2 py-3.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between ph-row-in"
                    :style="{ '--row-delay': `${index * 40}ms` }"
                >
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                            <MessageSquare class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-text truncate">
                                {{ entry.key ?? 'Unknown' }}
                                <span v-if="entry.version" class="ml-1.5 inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-600">v{{ entry.version }}</span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium truncate">
                                {{ entry.agent_profile?.name ?? entry.agent_type ?? '—' }}
                                <span v-if="entry.created_at || entry.updated_at" class="text-border mx-1">·</span>
                                {{ entry.updated_at ?? entry.created_at ?? '' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 pl-12 sm:pl-0">
                        <StatusBadge v-if="entry.status" :status="entry.status" :variant="statusVariant(entry.status)" />
                        <Link
                            v-if="entry.id"
                            :href="`/admin/prompts/${entry.id}`"
                            class="group/link inline-flex items-center gap-1.5 text-[12px] font-bold text-primary transition-colors hover:text-primary-dark"
                        >
                            View
                            <ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.ph-card-in {
    animation: ph-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}
@keyframes ph-card-entrance {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.ph-row-in {
    animation: ph-row-entrance 350ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--row-delay, 0ms);
}
@keyframes ph-row-entrance {
    from { opacity: 0; transform: translateX(-8px); }
    to   { opacity: 1; transform: translateX(0); }
}
</style>
