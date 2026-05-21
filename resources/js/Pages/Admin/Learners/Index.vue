<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { ArrowRight, Eye, Filter, GraduationCap, Loader2, Plus, RotateCcw, Search } from 'lucide-vue-next';

defineProps({ learners: Object, filters: Object, filterOptions: Object });
const filtering = ref(false);
const filter = (event) => { filtering.value = true; router.get('/admin/learners', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true, onFinish: () => { filtering.value = false; } }); };
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Learners</h1>
                <p class="mt-1 text-sm font-medium text-muted">{{ learners.total }} records found</p>
            </div>
            <Link href="/admin/learners/create" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                <Plus class="size-4" />
                Create
            </Link>
        </div>

        <DashboardCard class="lr-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500"><Filter class="size-4" /></div>
                <h2 class="text-sm font-bold text-text">Filter Learners</h2>
            </div>
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5" @submit.prevent="filter">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative"><Search class="absolute left-3 top-3 size-4 text-muted" /><input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search learners"></div>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">School</span>
                    <select name="school_id" :value="filters.school_id" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All schools</option><option v-for="school in filterOptions.schools" :key="school.value" :value="school.value">{{ school.label }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Class</span>
                    <select name="class_id" :value="filters.class_id" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All classes</option><option v-for="klass in filterOptions.classes" :key="klass.value" :value="klass.value">{{ klass.label }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Status</span>
                    <select name="status" :value="filters.status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Module</span>
                    <select name="current_module" :value="filters.current_module" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All modules</option><option v-for="module in filterOptions.modules" :key="module.value" :value="module.value">{{ module.label }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">CRLA Level</span>
                    <select name="crla_level" :value="filters.crla_level" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All CRLA levels</option><option v-for="level in filterOptions.crlaLevels" :key="level" :value="level">{{ level }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Reading Class</span>
                    <select name="reading_classification" :value="filters.reading_classification" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All classes</option><option v-for="classification in filterOptions.readingClassifications" :key="classification" :value="classification">{{ classification }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Diagnostic</span>
                    <select name="diagnostic_status" :value="filters.diagnostic_status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All statuses</option><option v-for="status in filterOptions.diagnosticStatuses" :key="status" :value="status">{{ status }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Final Status</span>
                    <select name="final_status" :value="filters.final_status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All statuses</option><option v-for="status in filterOptions.finalStatuses" :key="status" :value="status">{{ status }}</option></select>
                </label>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-5">
                    <button type="submit" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="filtering"><Loader2 v-if="filtering" class="size-4 animate-spin" /><Filter v-else class="size-4" />Filter</button>
                    <Link href="/admin/learners" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-5 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]"><RotateCcw class="size-4" />Reset</Link>
                </div>
            </form>
        </DashboardCard>

        <DashboardCard class="mt-5 lr-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-500"><GraduationCap class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Learner Profiles</h2>
                </div>
                <StatusBadge v-if="learners.data.length" :status="`${learners.total} learners`" />
            </div>

            <EmptyState v-if="learners.data.length === 0" title="No learners found" message="No learners match the selected filters." />

            <div v-else class="hidden sm:block overflow-hidden rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead><tr class="bg-background">
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Learner</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">School/Class</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Stage</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Module</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Status</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Action</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(learner, index) in learners.data" :key="learner.public_id" class="group transition-colors duration-150 hover:bg-primary-light/40 lr-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-xs font-bold">{{ (learner.first_name ?? '?').charAt(0).toUpperCase() }}</div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-text group-hover:text-primary transition-colors">{{ learner.learner_code }}</p>
                                        <p class="text-[11px] font-medium text-muted">{{ learner.first_name }} {{ learner.last_name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-text">{{ learner.school?.name }}</p>
                                <p class="text-[11px] font-medium text-muted">{{ learner.school_class?.name ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">{{ learner.current_stage ?? '-' }}</span></td>
                            <td class="px-4 py-3 font-medium text-muted">{{ learner.current_module?.title ?? '-' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="learner.is_active ? 'Active' : 'Inactive'" :variant="learner.is_active ? 'success' : 'danger'" /></td>
                            <td class="px-4 py-3 text-right"><Link :href="`/admin/learners/${learner.public_id}`" class="group/link inline-flex items-center gap-1.5 text-[13px] font-bold text-primary transition-colors hover:text-primary-dark"><Eye class="size-3.5" />View<ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" /></Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="learners.data.length > 0" class="sm:hidden divide-y divide-border/60">
                <Link v-for="(learner, index) in learners.data" :key="'l-' + learner.public_id" :href="`/admin/learners/${learner.public_id}`" class="group flex items-center justify-between gap-3 py-3.5 first:pt-0 last:pb-0 lr-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-600 text-sm font-bold">{{ (learner.first_name ?? '?').charAt(0).toUpperCase() }}</div>
                        <div class="min-w-0">
                            <p class="font-bold text-sm text-text truncate group-hover:text-primary transition-colors">{{ learner.learner_code }}</p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium truncate">{{ learner.first_name }} {{ learner.last_name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <StatusBadge :status="learner.is_active ? 'Active' : 'Inactive'" :variant="learner.is_active ? 'success' : 'danger'" />
                        <ArrowRight class="size-4 text-slate-300 group-hover:text-primary group-hover:translate-x-0.5 transition-all duration-200" />
                    </div>
                </Link>
            </div>

            <div v-if="learners.links && learners.last_page > 1" class="mt-5 flex items-center justify-center gap-1.5 border-t border-border/60 pt-4">
                <Link v-for="link in learners.links" :key="link.label" :href="link.url ?? '#'" class="inline-flex min-w-[36px] items-center justify-center rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-all duration-200" :class="link.active ? 'bg-primary text-white shadow-sm shadow-primary/20' : link.url ? 'text-slate-500 hover:bg-primary-light hover:text-primary' : 'text-slate-300 cursor-not-allowed'" v-html="link.label" />
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.lr-card-in { animation: lr-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes lr-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.lr-row-in { animation: lr-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes lr-row { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: translateX(0); } }
</style>
