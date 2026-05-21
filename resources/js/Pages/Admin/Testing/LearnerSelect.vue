<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import {
    ArrowLeft,
    ArrowRight,
    Filter,
    GraduationCap,
    Loader2,
    RotateCcw,
    School,
    Search,
    Users,
} from 'lucide-vue-next';

defineProps({ learners: Object, filters: Object, filterOptions: Object });

const filtering = ref(false);

const filter = (event) => {
    filtering.value = true;
    router.get('/admin/testing/learners', Object.fromEntries(new FormData(event.target).entries()), {
        preserveState: true,
        onFinish: () => { filtering.value = false; },
    });
};
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Select Learner for Testing</h1>
                <p class="mt-1 text-sm font-medium text-muted">Choose a learner to begin sandbox testing, flow jumps, or debug sessions.</p>
            </div>
            <Link href="/admin/testing" class="group inline-flex shrink-0 w-full md:w-auto items-center justify-center gap-2 rounded-xl bg-background px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:shadow-sm">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to QA Mode
            </Link>
        </div>

        <!-- ── Filters card ────────────────────────────────── -->
        <DashboardCard class="ls-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                    <Filter class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Filter Learners</h2>
            </div>

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="filter">
                <!-- Search -->
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative">
                        <Search class="absolute left-3 top-3 size-4 text-muted" />
                        <input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search learner by name or code">
                    </div>
                </label>

                <!-- School -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">School</span>
                    <select name="school_id" :value="filters.school_id" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="">All schools</option>
                        <option v-for="school in filterOptions.schools" :key="school.value" :value="school.value">{{ school.label }}</option>
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
                        href="/admin/testing/learners"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]"
                    >
                        <RotateCcw class="size-4" />
                        Reset
                    </Link>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Learners list ───────────────────────────────── -->
        <DashboardCard class="mt-5 ls-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <Users class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Learners</h2>
                </div>
                <StatusBadge v-if="learners.data.length" :status="`${learners.total ?? learners.data.length} found`" />
            </div>

            <!-- Empty state -->
            <EmptyState
                v-if="learners.data.length === 0"
                title="No learners found"
                message="No learners match the selected filters. Try adjusting or resetting your filters."
            />

            <!-- Learner rows -->
            <div v-else class="divide-y divide-border/60">
                <div
                    v-for="(learner, index) in learners.data"
                    :key="learner.public_id"
                    class="flex flex-col gap-3 py-3.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between ls-row-in"
                    :style="{ '--row-delay': `${index * 40}ms` }"
                >
                    <!-- Learner info -->
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 text-sm font-bold">
                            {{ (learner.first_name ?? learner.learner_code ?? '?').charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-text truncate">
                                <span class="font-bold text-primary">{{ learner.learner_code }}</span>
                                <span class="text-muted font-medium mx-1">&mdash;</span>
                                {{ learner.first_name }} {{ learner.last_name }}
                            </p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-[11px] text-muted font-medium truncate">
                                <School class="size-3 shrink-0" />
                                {{ learner.school?.name ?? 'No school' }}
                                <span v-if="learner.school_class?.name" class="text-border">/</span>
                                <span v-if="learner.school_class?.name">{{ learner.school_class.name }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Action -->
                    <Link
                        :href="`/admin/testing/learner/${learner.public_id}/jump`"
                        class="group inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-primary/5 border border-primary/10 px-4 py-2 text-[13px] font-bold text-primary transition-all duration-200 hover:bg-primary hover:text-white hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]"
                    >
                        <GraduationCap class="size-4" />
                        Use for testing
                        <ArrowRight class="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5" />
                    </Link>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="learners.links && learners.last_page > 1" class="mt-5 flex items-center justify-center gap-1.5 border-t border-border/60 pt-4">
                <Link
                    v-for="link in learners.links"
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
.ls-card-in {
    animation: ls-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}

@keyframes ls-card-entrance {
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
.ls-row-in {
    animation: ls-row-entrance 350ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--row-delay, 0ms);
}

@keyframes ls-row-entrance {
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
