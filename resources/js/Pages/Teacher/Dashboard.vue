<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';

defineProps({ stats: Object });
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Teacher Dashboard" subtitle="Class reading overview" />
        <div class="grid gap-4 md:grid-cols-3">
            <ScoreCard label="Learners" :value="stats.learners" />
            <ScoreCard label="Assessments" :value="stats.assessments" />
            <ScoreCard label="Needs review" :value="stats.needs_review" />
        </div>
        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Recent activity</h2>
            <div class="mt-4">
                <DataTable :headers="['Learner', 'Status', 'Next step']" :rows="[{ Learner: 'Mika Reader', Status: 'Diagnostic ready', 'Next step': 'Task 1' }]" />
            </div>
        </DashboardCard>
    </TeacherLayout>
</template>
