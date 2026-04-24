<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';

defineProps({ analytics: Object });

const rows = (object) => Object.entries(object ?? {}).map(([label, count]) => ({ label, count }));
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Class Analytics" subtitle="Aggregate reading progress" />
        <div class="grid gap-4 md:grid-cols-4">
            <ScoreCard label="Average CRLA" :value="analytics.averageCrlaTotalScore" />
            <ScoreCard label="Average Reading" :value="analytics.averageFinalReadingScore" />
            <ScoreCard label="Need Module 1" :value="analytics.moduleNeeds.module_1" />
            <ScoreCard label="No Module Needed" :value="analytics.moduleNeeds.none" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <DashboardCard>
                <h2 class="mb-4 text-xl font-black text-text">Module Distribution</h2>
                <DataTable :headers="['label', 'count']" :rows="rows(analytics.moduleDistribution)" />
            </DashboardCard>
            <DashboardCard>
                <h2 class="mb-4 text-xl font-black text-text">CRLA Distribution</h2>
                <DataTable v-if="rows(analytics.crlaDistribution).length" :headers="['label', 'count']" :rows="rows(analytics.crlaDistribution)" />
                <EmptyState v-else title="No CRLA data yet" />
            </DashboardCard>
            <DashboardCard>
                <h2 class="mb-4 text-xl font-black text-text">Reading Distribution</h2>
                <DataTable v-if="rows(analytics.readingDistribution).length" :headers="['label', 'count']" :rows="rows(analytics.readingDistribution)" />
                <EmptyState v-else title="No reading data yet" />
            </DashboardCard>
        </div>

        <DashboardCard class="mt-6">
            <h2 class="mb-4 text-xl font-black text-text">Recent Mastery Outcomes</h2>
            <DataTable v-if="analytics.recentMasteryOutcomes.length" :headers="['learner', 'module', 'score', 'decision', 'date']" :rows="analytics.recentMasteryOutcomes" />
            <EmptyState v-else title="No mastery outcomes yet" />
        </DashboardCard>
    </TeacherLayout>
</template>
