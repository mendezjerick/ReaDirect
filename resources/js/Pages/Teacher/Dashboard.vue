<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';

defineProps({ dashboard: Object });
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Teacher Dashboard" subtitle="Class reading overview" />
        <div class="grid gap-4 md:grid-cols-4">
            <ScoreCard label="Learners" :value="dashboard.counts.total_learners" />
            <ScoreCard label="Diagnostic complete" :value="dashboard.counts.diagnostic_completed" />
            <ScoreCard label="Diagnostic pending" :value="dashboard.counts.diagnostic_pending" />
            <ScoreCard label="Ready for reassessment" :value="dashboard.counts.ready_for_reassessment" />
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <ScoreCard label="Final reassessments complete" :value="dashboard.counts.final_reassessment_completed ?? 0" />
            <DashboardCard>
                <h2 class="text-lg font-black text-text">Final Reading Classifications</h2>
                <div class="mt-3 grid gap-2">
                    <div v-for="(count, label) in dashboard.finalReadingDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3">
                        <span class="font-bold text-muted">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.finalReadingDistribution ?? {}).length === 0" title="No final reassessment data yet" />
                </div>
            </DashboardCard>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <DashboardCard>
                <h2 class="text-lg font-black text-text">Learners by Module</h2>
                <div class="mt-4 grid gap-3">
                    <div v-for="(count, label) in dashboard.moduleDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3">
                        <span class="font-bold text-muted">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                </div>
            </DashboardCard>
            <DashboardCard>
                <h2 class="text-lg font-black text-text">CRLA Levels</h2>
                <div class="mt-4 grid gap-3">
                    <div v-for="(count, label) in dashboard.crlaDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3">
                        <span class="font-bold text-muted">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.crlaDistribution).length === 0" title="No diagnostic data yet" />
                </div>
            </DashboardCard>
            <DashboardCard>
                <h2 class="text-lg font-black text-text">Reading Classifications</h2>
                <div class="mt-4 grid gap-3">
                    <div v-for="(count, label) in dashboard.readingDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3">
                        <span class="font-bold text-muted">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.readingDistribution).length === 0" title="No reading data yet" />
                </div>
            </DashboardCard>
        </div>

        <DashboardCard class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-black text-text">Recent activity</h2>
                <div class="flex gap-2">
                    <Link href="/teacher/learners" class="rounded-xl bg-primary px-4 py-2 text-sm font-black text-white">Learner List</Link>
                    <Link href="/teacher/reports" class="rounded-xl bg-primary-light px-4 py-2 text-sm font-black text-primary">Reports</Link>
                </div>
            </div>
            <div class="mt-4">
                <DataTable v-if="dashboard.recentActivity.length" :headers="['learner', 'activity', 'date']" :rows="dashboard.recentActivity" />
                <EmptyState v-else title="No recent activity" message="Learner diagnostic and module activity will appear here." />
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
