<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import DataTable from '../../Components/DataTable.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import {
    Users,
    CheckCircle2,
    Clock,
    RefreshCw,
    Award,
    BookOpen,
    BarChart2,
    BookMarked,
    ClipboardCheck,
} from 'lucide-vue-next';

defineProps({ dashboard: Object });
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Teacher Dashboard" subtitle="Class reading overview and learner progress" />

        <!-- ── Stat cards ─────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ScoreCard label="Total Learners"         :value="dashboard.counts.total_learners"           :icon="Users"        color="orange" />
            <ScoreCard label="Diagnostic Complete"    :value="dashboard.counts.diagnostic_completed"     :icon="CheckCircle2" color="green"  />
            <ScoreCard label="Diagnostic Pending"     :value="dashboard.counts.diagnostic_pending"       :icon="Clock"        color="blue"   />
            <ScoreCard label="Ready for Reassessment" :value="dashboard.counts.ready_for_reassessment"  :icon="RefreshCw"    color="purple" />
        </div>

        <!-- ── Final reassessment + Reading classification ── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <ScoreCard label="Final Reassessments Complete" :value="dashboard.counts.final_reassessment_completed ?? 0" :icon="Award" color="green" />
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                        <BookMarked :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Final Reading Classifications</h2>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(count, label) in dashboard.finalReadingDistribution"
                        :key="label"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors hover:bg-orange-50/60"
                    >
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" variant="warning" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.finalReadingDistribution ?? {}).length === 0" title="No final reassessment data yet" />
                </div>
            </DashboardCard>
        </div>

        <!-- ── Distribution cards ─────────────────────────── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <!-- Learners by Module -->
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                        <BookOpen :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Learners by Module</h2>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(count, label) in dashboard.moduleDistribution"
                        :key="label"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors hover:bg-amber-50/60"
                    >
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                </div>
            </DashboardCard>

            <!-- CRLA Levels -->
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                        <BarChart2 :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">CRLA Levels</h2>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(count, label) in dashboard.crlaDistribution"
                        :key="label"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors hover:bg-emerald-50/60"
                    >
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.crlaDistribution).length === 0" title="No diagnostic data yet" />
                </div>
            </DashboardCard>

            <!-- Reading Classifications -->
            <DashboardCard>
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                        <BookMarked :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Reading Classifications</h2>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(count, label) in dashboard.readingDistribution"
                        :key="label"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors hover:bg-violet-50/60"
                    >
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.readingDistribution).length === 0" title="No reading data yet" />
                </div>
            </DashboardCard>
        </div>

        <!-- ── Recent Activity ────────────────────────────── -->
        <DashboardCard class="mt-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                        <ClipboardCheck :size="15" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Recent Activity</h2>
                </div>
                <div class="flex gap-2">
                    <Link href="/teacher/learners/create" class="rounded-xl bg-orange-500 px-4 py-2 text-[13px] font-bold text-white transition-colors hover:bg-orange-600">Create Learner</Link>
                    <Link href="/teacher/learners" class="rounded-xl bg-orange-500 px-4 py-2 text-[13px] font-bold text-white transition-colors hover:bg-orange-600">Learner List</Link>
                    <Link href="/teacher/reports" class="rounded-xl bg-background px-4 py-2 text-[13px] font-bold text-text border border-border/60 transition-colors hover:bg-slate-100">Reports</Link>
                </div>
            </div>
            <DataTable v-if="dashboard.recentActivity.length" :headers="['learner', 'activity', 'date']" :rows="dashboard.recentActivity" />
            <EmptyState v-else title="No recent activity" message="Learner diagnostic and module activity will appear here." />
        </DashboardCard>
    </TeacherLayout>
</template>
