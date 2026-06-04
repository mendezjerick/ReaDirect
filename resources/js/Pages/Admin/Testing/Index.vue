<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import ScoreCard from '../../../Components/ScoreCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    GraduationCap,
    ClipboardCheck,
    BookOpen,
    FileSearch,
    Search,
    Filter,
    RotateCcw,
    Loader2,
    ClipboardList,
    ArrowRight,
    Inbox,
} from 'lucide-vue-next';

const props = defineProps({ learnersCount: Number, sandboxAssessments: Array, sandboxModules: Array, filters: Object, filterOptions: Object });

const filtering = ref(false);

const filter = async (event) => {
    filtering.value = true;
    router.get('/admin/testing', Object.fromEntries(new FormData(event.target).entries()), {
        preserveState: true,
        onFinish: () => { filtering.value = false; },
    });
};

const statusVariant = (status) => {
    if (!status) return 'primary';
    const s = String(status).toLowerCase();
    if (s === 'completed' || s === 'passed') return 'success';
    if (s === 'failed' || s === 'error') return 'danger';
    if (s === 'in_progress' || s === 'pending') return 'warning';
    return 'primary';
};

const allAttempts = [
    ...(props.sandboxAssessments ?? []).map(item => ({
        id: item.public_id,
        href: `/admin/testing/assessment/${item.public_id}/debug`,
        learner: item.learner?.learner_code ?? '?',
        label: item.attempt_type,
        status: item.status,
        type: 'assessment',
    })),
    ...(props.sandboxModules ?? []).map(item => ({
        id: item.public_id ?? item.id,
        href: `/admin/testing/module/${item.id}/debug`,
        learner: item.learner?.learner_code ?? '?',
        label: item.module?.title ?? 'Module',
        status: item.status,
        type: 'module',
    })),
];
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-text">Testing / QA Mode</h1>
            <p class="mt-1 text-sm font-medium text-muted">Admin-only Tester QA flow jumps, sandbox attempts, STT debug, and LLM debug.</p>
        </div>

        <!-- ── Stat cards ──────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ScoreCard label="QA Testing" value="Tester" :icon="GraduationCap" color="blue" subtitle="dedicated QA learner">
                <template #footer>
                    <Link href="/admin/testing/flow-jump" class="group mt-3 inline-flex items-center gap-1.5 text-[12px] font-bold text-primary transition-colors hover:text-primary-dark">
                        Open QA Testing
                        <ArrowRight class="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5" />
                    </Link>
                </template>
            </ScoreCard>
            <ScoreCard label="Assessment Attempts" :value="sandboxAssessments.length" :icon="ClipboardCheck" color="green" subtitle="sandbox records" />
            <ScoreCard label="Module Attempts" :value="sandboxModules.length" :icon="BookOpen" color="purple" subtitle="sandbox records" />

            <!-- True Sandbox CTA card -->
            <article class="group relative rounded-2xl bg-surface border border-border/60 p-5 transition-all duration-200 hover:shadow-lg hover:shadow-black/[0.04] hover:-translate-y-0.5 qa-card-in" style="--card-delay: 120ms">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">True Sandbox</p>
                        <p class="mt-2 text-sm font-semibold text-text leading-snug">Direct ASR testing without learner progression.</p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-orange-50 text-orange-500 ring-4 ring-orange-100 transition-transform duration-200 group-hover:scale-105">
                        <FileSearch :size="20" />
                    </div>
                </div>
                <Link href="/admin/testing/true-sandbox" class="group/link mt-3 inline-flex items-center gap-1.5 text-[12px] font-bold text-primary transition-colors hover:text-primary-dark">
                    Open True Sandbox
                    <ArrowRight class="size-3.5 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                </Link>
            </article>
        </div>

        <!-- ── Filters card ────────────────────────────────── -->
        <DashboardCard class="mt-5 qa-card-in" style="--card-delay: 180ms">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                    <Filter class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Filter Attempts</h2>
            </div>

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" @submit.prevent="filter">
                <!-- Search -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Learner</span>
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 size-4 text-muted" />
                        <input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search learner">
                    </div>
                </label>

                <!-- Attempt type -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Attempt type</span>
                    <select name="attempt_type" :value="filters.attempt_type" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option v-for="type in filterOptions.attemptTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>

                <!-- Sandbox -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Sandbox</span>
                    <select name="sandbox" :value="filters.sandbox" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option v-for="option in filterOptions.sandbox" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>

                <!-- Module -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Module</span>
                    <select name="module" :value="filters.module" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">All modules</option>
                        <option v-for="module in filterOptions.modules" :key="module.value" :value="module.value">{{ module.label }}</option>
                    </select>
                </label>

                <!-- Date from -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Date from</span>
                    <input name="date_from" :value="filters.date_from" type="date" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                </label>

                <!-- Date to -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Date to</span>
                    <input name="date_to" :value="filters.date_to" type="date" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                </label>

                <!-- Status -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Status</span>
                    <input name="status" :value="filters.status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Status">
                </label>

                <!-- Actions -->
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3 xl:col-span-1">
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
                        href="/admin/testing"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]"
                    >
                        <RotateCcw class="size-4" />
                        Reset
                    </Link>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Recent Attempts ─────────────────────────────── -->
        <DashboardCard class="mt-5 qa-card-in" style="--card-delay: 260ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <ClipboardList class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Recent Attempts</h2>
                </div>
                <StatusBadge v-if="allAttempts.length" :status="`${allAttempts.length} total`" />
            </div>

            <!-- Empty state -->
            <EmptyState
                v-if="allAttempts.length === 0"
                title="No records found"
                message="No attempts match the selected filters. Try adjusting or resetting your filters."
            />

            <!-- Attempts list -->
            <div v-else class="divide-y divide-border/60">
                <Link
                    v-for="(item, index) in allAttempts"
                    :key="item.id"
                    :href="item.href"
                    class="group flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 transition-colors duration-150 hover:bg-primary-light/40 -mx-2 px-2 rounded-xl qa-row-in"
                    :style="{ '--row-delay': `${index * 40}ms` }"
                >
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                             :class="item.type === 'assessment' ? 'bg-blue-50 text-blue-600' : 'bg-violet-50 text-violet-600'">
                            {{ item.learner.charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-sm text-text group-hover:text-primary transition-colors">
                                {{ item.learner }}
                                <span class="text-muted font-medium">&mdash;</span>
                                {{ item.label }}
                            </p>
                            <p class="text-[11px] text-muted font-medium">{{ item.type === 'assessment' ? 'Assessment' : 'Module' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <StatusBadge :status="item.status" :variant="statusVariant(item.status)" />
                        <ArrowRight class="size-4 text-slate-300 transition-all duration-200 group-hover:text-primary group-hover:translate-x-0.5" />
                    </div>
                </Link>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
/* ─── Staggered card entrance ─────────────────────────── */
.qa-card-in {
    animation: qa-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}

@keyframes qa-card-entrance {
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
.qa-row-in {
    animation: qa-row-entrance 350ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--row-delay, 0ms);
}

@keyframes qa-row-entrance {
    from {
        opacity: 0;
        transform: translateX(-8px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>
