<script setup>
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
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
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Class Analytics</h1>
                <p class="mt-1 text-sm font-medium text-muted">Aggregate reading progress across your learners</p>
            </div>
        </div>

        <!-- ── Stat cards ─────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 ca-card-in">
            <ScoreCard label="Average CRLA"      :value="analytics.averageCrlaTotalScore"   :icon="TrendingUp" color="blue"   />
            <ScoreCard label="Average Reading"   :value="analytics.averageFinalReadingScore" :icon="BookMarked" color="green"  />
            <ScoreCard label="Need Module 1"     :value="analytics.moduleNeeds.module_1"     :icon="BookOpen"   color="orange" />
            <ScoreCard label="No Module Needed"  :value="analytics.moduleNeeds.none"         :icon="Award"      color="purple" />
        </div>

        <!-- ── Distribution tables ────────────────────────── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-3 ca-card-in" style="--card-delay: 80ms">
            <!-- Module Distribution -->
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500"><BookOpen class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Module Distribution</h2>
                </div>
                <div class="space-y-2">
                    <div v-for="row in rows(analytics.moduleDistribution)" :key="row.label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-blue-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ row.label }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-700">{{ row.count }}</span>
                    </div>
                    <EmptyState v-if="rows(analytics.moduleDistribution).length === 0" title="No module data yet" />
                </div>
            </DashboardCard>
            
            <!-- CRLA Distribution -->
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500"><BarChart2 class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">CRLA Distribution</h2>
                </div>
                <div class="space-y-2">
                    <div v-for="row in rows(analytics.crlaDistribution)" :key="row.label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-emerald-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ row.label }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-700">{{ row.count }}</span>
                    </div>
                    <EmptyState v-if="rows(analytics.crlaDistribution).length === 0" title="No CRLA data yet" />
                </div>
            </DashboardCard>

            <!-- Reading Distribution -->
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500"><BookMarked class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Reading Distribution</h2>
                </div>
                <div class="space-y-2">
                    <div v-for="row in rows(analytics.readingDistribution)" :key="row.label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-orange-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ row.label }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-orange-100 text-[11px] font-bold text-orange-700">{{ row.count }}</span>
                    </div>
                    <EmptyState v-if="rows(analytics.readingDistribution).length === 0" title="No reading data yet" />
                </div>
            </DashboardCard>
        </div>

        <!-- ── Recent mastery outcomes ────────────────────── -->
        <DashboardCard class="mt-6 ca-card-in" style="--card-delay: 160ms">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500"><Award class="size-4" /></div>
                <h2 class="text-sm font-bold text-text">Recent Mastery Outcomes</h2>
            </div>
            
            <div v-if="analytics.recentMasteryOutcomes.length" class="overflow-x-auto rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Learner</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Module</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-center">Score</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Decision</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(row, index) in analytics.recentMasteryOutcomes" :key="index" class="group transition-colors duration-150 hover:bg-violet-50/40">
                            <td class="px-4 py-3 font-bold text-text group-hover:text-violet-600 transition-colors">{{ row.learner }}</td>
                            <td class="px-4 py-3 font-medium text-muted">{{ row.module }}</td>
                            <td class="px-4 py-3 text-center"><span class="inline-flex h-6 min-w-[32px] items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-700 px-2">{{ row.score }}</span></td>
                            <td class="px-4 py-3"><span class="inline-flex items-center rounded-full bg-violet-100 px-2.5 py-0.5 text-[11px] font-bold text-violet-700">{{ row.decision }}</span></td>
                            <td class="px-4 py-3 font-medium text-muted text-right">{{ row.date }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No mastery outcomes yet" message="Mastery assessment results will appear here." />
        </DashboardCard>
    </TeacherLayout>
</template>

<style scoped>
.ca-card-in { animation: ca-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes ca-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
