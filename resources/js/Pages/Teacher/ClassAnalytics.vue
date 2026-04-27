<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import {
    TrendingUp,
    BookOpen,
    BarChart2,
    BookMarked,
    Award,
} from 'lucide-vue-next';

defineProps({ analytics: Object });

const rows = (object) => Object.entries(object ?? {}).map(([label, count]) => ({ label, count }));
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Class Analytics" subtitle="Aggregate reading progress across your learners" />

        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ScoreCard label="Average CRLA"      :value="analytics.averageCrlaTotalScore"   :icon="TrendingUp" color="blue"   />
            <ScoreCard label="Average Reading"   :value="analytics.averageFinalReadingScore" :icon="BookMarked" color="green"  />
            <ScoreCard label="Need Module 1"     :value="analytics.moduleNeeds.module_1"     :icon="BookOpen"   color="orange" />
            <ScoreCard label="No Module Needed"  :value="analytics.moduleNeeds.none"         :icon="Award"      color="purple" />
        </div>

        <!-- Distribution tables -->
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                        <BookOpen :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Module Distribution</h2>
                </div>
                <DataTable :headers="['label', 'count']" :rows="rows(analytics.moduleDistribution)" />
            </DashboardCard>
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                        <BarChart2 :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">CRLA Distribution</h2>
                </div>
                <DataTable v-if="rows(analytics.crlaDistribution).length" :headers="['label', 'count']" :rows="rows(analytics.crlaDistribution)" />
                <EmptyState v-else title="No CRLA data yet" />
            </DashboardCard>
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                        <BookMarked :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Reading Distribution</h2>
                </div>
                <DataTable v-if="rows(analytics.readingDistribution).length" :headers="['label', 'count']" :rows="rows(analytics.readingDistribution)" />
                <EmptyState v-else title="No reading data yet" />
            </DashboardCard>
        </div>

        <!-- Recent mastery outcomes -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                    <Award :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Recent Mastery Outcomes</h2>
            </div>
            <DataTable v-if="analytics.recentMasteryOutcomes.length" :headers="['learner', 'module', 'score', 'decision', 'date']" :rows="analytics.recentMasteryOutcomes" />
            <EmptyState v-else title="No mastery outcomes yet" />
        </DashboardCard>
    </TeacherLayout>
</template>
