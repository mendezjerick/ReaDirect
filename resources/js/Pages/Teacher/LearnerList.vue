<script setup>
import { Link, router } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { ArrowRight, Eye, Filter, GraduationCap, Plus, Search } from 'lucide-vue-next';

const props = defineProps({ learners: Array, filters: Object });

const submitFilters = (event) => {
    const data = Object.fromEntries(new FormData(event.target).entries());
    router.get('/teacher/learners', data, { preserveState: true, replace: true });
};
</script>

<template>
    <TeacherLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Learners</h1>
                <p class="mt-1 text-sm font-medium text-muted">Assigned class roster ({{ learners.length }} learners)</p>
            </div>
            <Link href="/teacher/learners/create" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-orange-600 hover:shadow-md hover:shadow-orange-500/20 active:scale-[0.97]">
                <Plus class="size-4" />
                Create Learner
            </Link>
        </div>

        <!-- ── Filters ─────────────────────────────────────── -->
        <DashboardCard class="ll-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500"><Filter class="size-4" /></div>
                <h2 class="text-sm font-bold text-text">Filter Roster</h2>
            </div>
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submitFilters">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative"><Search class="absolute left-3 top-3 size-4 text-muted" /><input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-orange-500/40 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10" placeholder="Search name or code"></div>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Module</span>
                    <select name="module" :value="filters.module" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-orange-500/40 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10"><option value="">All modules</option><option value="module_1">Module 1</option><option value="module_2">Module 2</option><option value="module_3">Module 3</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">CRLA Level</span>
                    <input name="crla" :value="filters.crla" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-orange-500/40 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10" placeholder="e.g. Full Refresher">
                </label>
                <div class="flex items-end sm:col-span-2 lg:col-span-4">
                    <button class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-orange-600 hover:shadow-md hover:shadow-orange-500/20 active:scale-[0.97]">
                        <Filter class="size-4" /> Filter
                    </button>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Learner table ───────────────────────────────── -->
        <DashboardCard class="mt-5 ll-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500"><GraduationCap class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Class Roster</h2>
                </div>
                <StatusBadge v-if="learners.length" :status="`${learners.length} learners`" />
            </div>

            <EmptyState v-if="!learners.length" title="No learners found" message="Adjust filters or confirm class assignments." />

            <div v-else class="hidden sm:block overflow-hidden rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead><tr class="bg-background">
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Learner</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Class</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Stage</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Module / CRLA</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Reading / Diagnostic</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Mastery / Activity</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Action</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(learner, index) in learners" :key="learner.public_id" class="group transition-colors duration-150 hover:bg-orange-50/40 ll-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-xs font-bold">{{ (learner.name ?? '?').charAt(0).toUpperCase() }}</div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-text group-hover:text-orange-600 transition-colors">{{ learner.learner_code }}</p>
                                        <p class="text-[11px] font-medium text-muted">{{ learner.name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-muted">{{ learner.class ?? '-' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="learner.current_stage ?? 'Not Started'" /></td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-text">{{ learner.current_module ?? '-' }}</p>
                                <p class="text-[11px] font-medium text-muted">{{ learner.crla_level ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-text">{{ learner.reading_classification ?? '-' }}</p>
                                <p class="text-[11px] font-medium text-muted">{{ learner.diagnostic_status }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-text">{{ learner.latest_mastery_decision ?? '-' }}</p>
                                <p class="text-[11px] font-medium text-muted">{{ learner.last_activity_date ?? 'No activity' }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link class="group/link inline-flex items-center gap-1.5 text-[13px] font-bold text-orange-500 transition-colors hover:text-orange-600" :href="`/teacher/learners/${learner.public_id}`">
                                    <Eye class="size-3.5" /> View
                                    <ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile view -->
            <div v-if="learners.length > 0" class="sm:hidden divide-y divide-border/60">
                <Link v-for="(learner, index) in learners" :key="'m-' + learner.public_id" :href="`/teacher/learners/${learner.public_id}`" class="group flex items-center justify-between gap-3 py-3.5 first:pt-0 last:pb-0 ll-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-sm font-bold">{{ (learner.name ?? '?').charAt(0).toUpperCase() }}</div>
                        <div class="min-w-0">
                            <p class="font-bold text-sm text-text truncate group-hover:text-orange-600 transition-colors">{{ learner.learner_code }}</p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium truncate">{{ learner.name }} · {{ learner.current_module ?? 'No Module' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <StatusBadge :status="learner.current_stage ?? 'Not Started'" />
                        <ArrowRight class="size-4 text-slate-300 group-hover:text-orange-500 group-hover:translate-x-0.5 transition-all duration-200" />
                    </div>
                </Link>
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>

<style scoped>
.ll-card-in { animation: ll-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes ll-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.ll-row-in { animation: ll-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes ll-row { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: translateX(0); } }
</style>
