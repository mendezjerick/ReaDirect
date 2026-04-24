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
        <DashboardCard>
            <form class="grid gap-3 md:grid-cols-4" @submit.prevent="submitFilters">
                <input name="search" :value="filters.search" class="rounded-xl border border-border px-4 py-3" placeholder="Search name or code">
                <select name="module" :value="filters.module" class="rounded-xl border border-border px-4 py-3">
                    <option value="">All modules</option>
                    <option value="module_1">Module 1</option>
                    <option value="module_2">Module 2</option>
                    <option value="module_3">Module 3</option>
                </select>
                <input name="crla" :value="filters.crla" class="rounded-xl border border-border px-4 py-3" placeholder="CRLA level">
                <button class="rounded-xl bg-primary px-4 py-3 font-black text-white">Filter</button>
            </form>
        </DashboardCard>

        <DashboardCard class="mt-6">
            <div v-if="learners.length" class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-primary-light text-primary">
                        <tr>
                            <th class="px-4 py-3">Learner</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Stage</th>
                            <th class="px-4 py-3">Module</th>
                            <th class="px-4 py-3">CRLA</th>
                            <th class="px-4 py-3">Reading</th>
                            <th class="px-4 py-3">Diagnostic</th>
                            <th class="px-4 py-3">Mastery</th>
                            <th class="px-4 py-3">Last Activity</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="learner in learners" :key="learner.public_id" class="border-t border-border">
                            <td class="px-4 py-3 font-bold text-text">{{ learner.learner_code }}<br><span class="text-muted">{{ learner.name }}</span></td>
                            <td class="px-4 py-3 text-muted">{{ learner.class ?? '-' }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="learner.current_stage ?? 'Not Started'" /></td>
                            <td class="px-4 py-3 text-muted">{{ learner.current_module ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted">{{ learner.crla_level ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted">{{ learner.reading_classification ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted">{{ learner.diagnostic_status }}</td>
                            <td class="px-4 py-3 text-muted">{{ learner.latest_mastery_decision ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted">{{ learner.last_activity_date ?? '-' }}</td>
                            <td class="px-4 py-3"><Link class="font-black text-primary" :href="`/teacher/learners/${learner.public_id}`">View</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No learners found" message="Adjust filters or confirm class assignments." />
        </DashboardCard>
    </TeacherLayout>
</template>
