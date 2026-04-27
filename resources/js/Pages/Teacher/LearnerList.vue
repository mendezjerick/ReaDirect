<script setup>
import { Link, router } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';

const props = defineProps({ learners: Array, filters: Object });

const submitFilters = (event) => {
    const data = Object.fromEntries(new FormData(event.target).entries());
    router.get('/teacher/learners', data, { preserveState: true, replace: true });
};
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Learners" subtitle="Assigned class roster" />

        <!-- Filters -->
        <DashboardCard>
            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="submitFilters">
                <input name="search" :value="filters.search" class="rounded-xl border border-border/60 bg-background px-4 py-2.5 text-[13px] placeholder:text-muted focus:bg-surface" placeholder="Search name or code">
                <select name="module" :value="filters.module" class="rounded-xl border border-border/60 bg-background px-4 py-2.5 text-[13px] text-slate-600">
                    <option value="">All modules</option>
                    <option value="module_1">Module 1</option>
                    <option value="module_2">Module 2</option>
                    <option value="module_3">Module 3</option>
                </select>
                <input name="crla" :value="filters.crla" class="rounded-xl border border-border/60 bg-background px-4 py-2.5 text-[13px] placeholder:text-muted focus:bg-surface" placeholder="CRLA level">
                <button class="rounded-xl bg-orange-500 px-4 py-2.5 text-[13px] font-bold text-white transition-colors hover:bg-orange-600">Filter</button>
            </form>
        </DashboardCard>

        <!-- Table -->
        <DashboardCard class="mt-4">
            <div v-if="learners.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Learner</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Class</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Stage</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Module</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">CRLA</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Reading</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Diagnostic</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Mastery</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Last Activity</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted whitespace-nowrap">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="learner in learners" :key="learner.public_id" class="border-t border-border/40 transition-colors hover:bg-background/60">
                            <td class="px-4 py-3">
                                <p class="text-[13px] font-bold text-text">{{ learner.learner_code }}</p>
                                <p class="text-[11px] text-muted">{{ learner.name }}</p>
                            </td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.class ?? '-' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="learner.current_stage ?? 'Not Started'" /></td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.current_module ?? '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.crla_level ?? '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.reading_classification ?? '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.diagnostic_status }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.latest_mastery_decision ?? '-' }}</td>
                            <td class="px-4 py-3 text-[13px] text-slate-600">{{ learner.last_activity_date ?? '-' }}</td>
                            <td class="px-4 py-3"><Link class="text-[13px] font-bold text-orange-500 hover:text-orange-600" :href="`/teacher/learners/${learner.public_id}`">View</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No learners found" message="Adjust filters or confirm class assignments." />
        </DashboardCard>
    </TeacherLayout>
</template>
