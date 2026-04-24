<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import EmptyState from '../../Components/EmptyState.vue';

defineProps({ reports: Array, learners: Array });
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Reports" subtitle="CSV exports and PDF placeholders" />
        <div class="grid gap-4 md:grid-cols-2">
            <DashboardCard v-for="report in reports" :key="report.type">
                <h2 class="text-xl font-black text-text">{{ report.title }}</h2>
                <p class="mt-2 text-muted">{{ report.description }}</p>
            </DashboardCard>
        </div>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Learner Reports</h2>
            <div v-if="learners.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-primary-light text-primary">
                        <tr>
                            <th class="px-4 py-3">Learner</th>
                            <th class="px-4 py-3">Exports</th>
                            <th class="px-4 py-3">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="learner in learners" :key="learner.public_id" class="border-t border-border">
                            <td class="px-4 py-3 font-bold text-text">{{ learner.learner_code }} · {{ learner.name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a class="rounded-lg bg-primary px-3 py-2 font-black text-white" :href="`/teacher/reports/learner/${learner.public_id}/diagnostic`">Diagnostic CSV</a>
                                    <a class="rounded-lg bg-primary-light px-3 py-2 font-black text-primary" :href="`/teacher/reports/learner/${learner.public_id}/module-progress`">Module CSV</a>
                                    <a class="rounded-lg bg-primary-light px-3 py-2 font-black text-primary" :href="`/teacher/reports/learner/${learner.public_id}/full-progress`">Full CSV</a>
                                </div>
                            </td>
                            <td class="px-4 py-3"><a class="text-muted underline" href="/teacher/reports/pdf-placeholder">PDF later</a></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No learners available" />
        </DashboardCard>

        <DashboardCard class="mt-6">
            <h2 class="text-xl font-black text-text">Class Summary</h2>
            <a class="mt-4 inline-flex rounded-xl bg-primary px-4 py-3 font-black text-white" href="/teacher/reports/class-summary">Export Class CSV</a>
        </DashboardCard>
    </TeacherLayout>
</template>
