<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    ArrowRight,
    BookOpen,
    Clock,
    Filter,
    Gauge,
    Loader2,
    RotateCcw,
    Scale,
    Search,
    Target,
} from 'lucide-vue-next';

defineProps({ masteryThresholds: Array, classificationRules: Array, filters: Object, filterOptions: Object });

const filtering = ref(false);

const filter = (event) => {
    filtering.value = true;
    router.get('/admin/rules', Object.fromEntries(new FormData(event.target).entries()), {
        preserveState: true,
        onFinish: () => { filtering.value = false; },
    });
};

const scoreColor = (min, max) => {
    const mid = ((min ?? 0) + (max ?? 100)) / 2;
    if (mid >= 80) return 'bg-green-50 text-green-600 border-green-200/60';
    if (mid >= 50) return 'bg-amber-50 text-amber-600 border-amber-200/60';
    return 'bg-red-50 text-red-600 border-red-200/60';
};
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Rules & Thresholds</h1>
                <p class="mt-1 text-sm font-medium text-muted">Classification rules and module mastery score thresholds.</p>
            </div>
            <Link href="/admin/rules/history" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                <Clock class="size-4" />
                History
            </Link>
        </div>

        <!-- ── Filters ─────────────────────────────────────── -->
        <DashboardCard class="mb-5 rl-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500">
                    <Filter class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Filter Rules</h2>
            </div>

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="filter">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative">
                        <Search class="absolute left-3 top-3 size-4 text-muted" />
                        <input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search rules">
                    </div>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Rule type</span>
                    <select name="rule_type" :value="filters.rule_type" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option v-for="type in filterOptions.ruleTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="filtering">
                        <Loader2 v-if="filtering" class="size-4 animate-spin" />
                        <Filter v-else class="size-4" />
                        Filter
                    </button>
                    <Link href="/admin/rules" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                        <RotateCcw class="size-4" />
                        Reset
                    </Link>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Content grid ────────────────────────────────── -->
        <div class="grid gap-5 lg:grid-cols-2">
            <!-- Classification Rules -->
            <DashboardCard class="rl-card-in" style="--card-delay: 80ms">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                            <Scale class="size-4" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Classification Rules</h2>
                    </div>
                    <StatusBadge v-if="classificationRules.length" :status="`${classificationRules.length} rules`" />
                </div>

                <EmptyState v-if="classificationRules.length === 0" title="No classification rules" message="No rules match the current filters." />

                <div v-else class="space-y-2">
                    <div v-for="(rule, index) in classificationRules" :key="rule.name" class="rounded-xl border border-border/60 bg-background/50 p-4 transition-colors duration-150 hover:bg-slate-50 rl-row-in" :style="{ '--row-delay': `${index * 50}ms` }">
                        <h3 class="text-sm font-bold text-text">{{ rule.name }}</h3>
                        <p class="mt-1 text-[12px] leading-relaxed font-medium text-muted">{{ rule.rule }}</p>
                    </div>
                </div>
            </DashboardCard>

            <!-- Module Mastery Thresholds -->
            <DashboardCard class="rl-card-in" style="--card-delay: 160ms">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                            <Target class="size-4" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Module Mastery Thresholds</h2>
                    </div>
                    <StatusBadge v-if="masteryThresholds.length" :status="`${masteryThresholds.length} thresholds`" variant="success" />
                </div>

                <EmptyState v-if="masteryThresholds.length === 0" title="No thresholds" message="No mastery thresholds match the current filters." />

                <div v-else class="space-y-2">
                    <Link
                        v-for="(rule, index) in masteryThresholds"
                        :key="rule.id"
                        :href="`/admin/rules/${rule.id}`"
                        class="group flex items-center gap-3 rounded-xl border border-border/60 bg-background/50 p-3.5 transition-all duration-200 hover:bg-primary-light/40 hover:border-primary/20 rl-row-in"
                        :style="{ '--row-delay': `${index * 40}ms` }"
                    >
                        <!-- Score range badge -->
                        <div class="flex h-10 w-16 shrink-0 items-center justify-center rounded-lg border text-[12px] font-extrabold" :class="scoreColor(rule.min_score, rule.max_score)">
                            {{ rule.min_score }}-{{ rule.max_score ?? 100 }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm text-primary truncate group-hover:text-primary-dark transition-colors">
                                {{ rule.module?.title ?? 'Module' }}: {{ rule.min_score }}-{{ rule.max_score ?? '100' }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium truncate">
                                {{ rule.decision }} · {{ rule.rule_key }}
                            </p>
                        </div>
                        <ArrowRight class="size-4 shrink-0 text-slate-300 transition-all duration-200 group-hover:text-primary group-hover:translate-x-0.5" />
                    </Link>
                </div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.rl-card-in { animation: rl-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes rl-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.rl-row-in { animation: rl-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes rl-row { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
</style>
