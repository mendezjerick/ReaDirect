<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import { ArrowRight, Eye, Filter, Loader2, Plus, RotateCcw, Search, UserCheck } from 'lucide-vue-next';

defineProps({ teachers: Object, filters: Object, filterOptions: Object });
const filtering = ref(false);
const filter = (event) => { filtering.value = true; router.get('/admin/teachers', Object.fromEntries(new FormData(event.target).entries()), { preserveState: true, onFinish: () => { filtering.value = false; } }); };
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Teachers</h1>
                <p class="mt-1 text-sm font-medium text-muted">{{ teachers.total }} records found</p>
            </div>
            <Link href="/admin/teachers/create" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                <Plus class="size-4" />
                Create
            </Link>
        </div>

        <DashboardCard class="tc-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-500"><Filter class="size-4" /></div>
                <h2 class="text-sm font-bold text-text">Filter Teachers</h2>
            </div>
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="filter">
                <label class="grid gap-1.5 sm:col-span-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Search</span>
                    <div class="relative"><Search class="absolute left-3 top-3 size-4 text-muted" /><input name="search" :value="filters.search" class="w-full rounded-xl border border-border bg-white py-2.5 pl-9 pr-3 text-[13px] font-medium transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Search teachers"></div>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">School</span>
                    <select name="school_id" :value="filters.school_id" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option value="">All schools</option><option v-for="school in filterOptions.schools" :key="school.value" :value="school.value">{{ school.label }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Role</span>
                    <select name="role" :value="filters.role" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option v-for="role in filterOptions.roles" :key="role.value" :value="role.value">{{ role.label }}</option></select>
                </label>
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Status</span>
                    <select name="status" :value="filters.status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10"><option v-for="status in filterOptions.statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                </label>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                    <button type="submit" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="filtering"><Loader2 v-if="filtering" class="size-4 animate-spin" /><Filter v-else class="size-4" />Filter</button>
                    <Link href="/admin/teachers" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-5 py-2.5 text-sm font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]"><RotateCcw class="size-4" />Reset</Link>
                </div>
            </form>
        </DashboardCard>

        <DashboardCard class="mt-5 tc-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500"><UserCheck class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Teacher Profiles</h2>
                </div>
                <StatusBadge v-if="teachers.data.length" :status="`${teachers.total} teachers`" />
            </div>

            <EmptyState v-if="teachers.data.length === 0" title="No teachers found" message="No teachers match the selected filters." />

            <div v-else class="hidden sm:block overflow-hidden rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead><tr class="bg-background">
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Name</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Email</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-center">Classes</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Status</th>
                        <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Action</th>
                    </tr></thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(teacher, index) in teachers.data" :key="teacher.id" class="group transition-colors duration-150 hover:bg-primary-light/40 tc-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold">{{ (teacher.name ?? '?').charAt(0).toUpperCase() }}</div>
                                    <span class="font-semibold text-text group-hover:text-primary transition-colors">{{ teacher.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-muted">{{ teacher.email }}</td>
                            <td class="px-4 py-3 text-center"><span class="inline-flex items-center justify-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 min-w-[28px]">{{ teacher.teaching_classes_count }}</span></td>
                            <td class="px-4 py-3"><StatusBadge :status="teacher.is_active ? 'Active' : 'Inactive'" :variant="teacher.is_active ? 'success' : 'danger'" /></td>
                            <td class="px-4 py-3 text-right"><Link :href="`/admin/teachers/${teacher.id}`" class="group/link inline-flex items-center gap-1.5 text-[13px] font-bold text-primary transition-colors hover:text-primary-dark"><Eye class="size-3.5" />View<ArrowRight class="size-3 transition-transform duration-200 group-hover/link:translate-x-0.5" /></Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="teachers.data.length > 0" class="sm:hidden divide-y divide-border/60">
                <Link v-for="(teacher, index) in teachers.data" :key="'t-' + teacher.id" :href="`/admin/teachers/${teacher.id}`" class="group flex items-center justify-between gap-3 py-3.5 first:pt-0 last:pb-0 tc-row-in" :style="{ '--row-delay': `${index * 35}ms` }">
                    <div class="min-w-0 flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 text-sm font-bold">{{ (teacher.name ?? '?').charAt(0).toUpperCase() }}</div>
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-text truncate group-hover:text-primary transition-colors">{{ teacher.name }}</p>
                            <p class="mt-0.5 text-[11px] text-muted font-medium truncate">{{ teacher.email }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <StatusBadge :status="teacher.is_active ? 'Active' : 'Inactive'" :variant="teacher.is_active ? 'success' : 'danger'" />
                        <ArrowRight class="size-4 text-slate-300 group-hover:text-primary group-hover:translate-x-0.5 transition-all duration-200" />
                    </div>
                </Link>
            </div>

            <div v-if="teachers.links && teachers.last_page > 1" class="mt-5 flex items-center justify-center gap-1.5 border-t border-border/60 pt-4">
                <Link v-for="link in teachers.links" :key="link.label" :href="link.url ?? '#'" class="inline-flex min-w-[36px] items-center justify-center rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-all duration-200" :class="link.active ? 'bg-primary text-white shadow-sm shadow-primary/20' : link.url ? 'text-slate-500 hover:bg-primary-light hover:text-primary' : 'text-slate-300 cursor-not-allowed'" v-html="link.label" />
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.tc-card-in { animation: tc-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes tc-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.tc-row-in { animation: tc-row 350ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--row-delay, 0ms); }
@keyframes tc-row { from { opacity: 0; transform: translateX(-6px); } to { opacity: 1; transform: translateX(0); } }
</style>
