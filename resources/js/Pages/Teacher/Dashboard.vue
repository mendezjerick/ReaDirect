<script setup>
import { Link } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
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
    ArrowRight
} from 'lucide-vue-next';

defineProps({ dashboard: Object });
</script>

<template>
    <TeacherLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Teacher Dashboard</h1>
                <p class="mt-1 text-sm font-medium text-muted">Class reading overview and learner progress</p>
            </div>
        </div>

        <!-- ── Stat cards ─────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 td-card-in">
            <ScoreCard label="Total Learners"         :value="dashboard.counts.total_learners"           :icon="Users"        color="orange" />
            <ScoreCard label="Diagnostic Complete"    :value="dashboard.counts.diagnostic_completed"     :icon="CheckCircle2" color="green"  />
            <ScoreCard label="Diagnostic Pending"     :value="dashboard.counts.diagnostic_pending"       :icon="Clock"        color="blue"   />
            <ScoreCard label="Ready for Reassessment" :value="dashboard.counts.ready_for_reassessment"  :icon="RefreshCw"    color="purple" />
        </div>

        <!-- ── Final reassessment + Reading classification ── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2 td-card-in" style="--card-delay: 80ms">
            <ScoreCard label="Final Reassessments Complete" :value="dashboard.counts.final_reassessment_completed ?? 0" :icon="Award" color="green" />
            
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500"><BookMarked class="size-4" /></div>
                        <h2 class="text-sm font-bold text-text">Final Reading Classifications</h2>
                    </div>
                </div>
                <div class="space-y-2">
                    <div v-for="(count, label) in dashboard.finalReadingDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-orange-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" variant="warning" />
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.finalReadingDistribution ?? {}).length === 0" title="No final reassessment data yet" />
                </div>
            </DashboardCard>
        </div>

        <!-- ── Distribution cards ─────────────────────────── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-3 td-card-in" style="--card-delay: 160ms">
            <!-- Learners by Module -->
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500"><BookOpen class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Learners by Module</h2>
                </div>
                <div class="space-y-2">
                    <div v-for="(count, label) in dashboard.moduleDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-blue-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ label }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-700">{{ count }}</span>
                    </div>
                </div>
            </DashboardCard>

            <!-- CRLA Levels -->
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500"><BarChart2 class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">CRLA Levels</h2>
                </div>
                <div class="space-y-2">
                    <div v-for="(count, label) in dashboard.crlaDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-emerald-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ label }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-700">{{ count }}</span>
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.crlaDistribution).length === 0" title="No diagnostic data yet" />
                </div>
            </DashboardCard>

            <!-- Reading Classifications -->
            <DashboardCard class="h-full">
                <div class="mb-4 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500"><BookMarked class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Reading Classifications</h2>
                </div>
                <div class="space-y-2">
                    <div v-for="(count, label) in dashboard.readingDistribution" :key="label" class="flex items-center justify-between rounded-xl bg-background px-4 py-3 text-sm transition-colors hover:bg-violet-50/60 border border-border/40">
                        <span class="font-semibold text-text">{{ label }}</span>
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-violet-100 text-[11px] font-bold text-violet-700">{{ count }}</span>
                    </div>
                    <EmptyState v-if="Object.keys(dashboard.readingDistribution).length === 0" title="No reading data yet" />
                </div>
            </DashboardCard>
        </div>

        <!-- ── Recent Activity ────────────────────────────── -->
        <DashboardCard class="mt-6 td-card-in" style="--card-delay: 240ms">
            <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500"><ClipboardCheck class="size-4" /></div>
                    <h2 class="text-sm font-bold text-text">Recent Activity</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/teacher/learners/create" class="inline-flex items-center rounded-xl bg-orange-500 px-3.5 py-2 text-[12px] font-bold text-white transition-all duration-200 hover:bg-orange-600 hover:shadow-sm active:scale-[0.97]">Create Learner</Link>
                    <Link href="/teacher/learners" class="inline-flex items-center rounded-xl bg-orange-50 px-3.5 py-2 text-[12px] font-bold text-orange-600 transition-all duration-200 hover:bg-orange-100 active:scale-[0.97]">Learner List</Link>
                    <Link href="/teacher/reports" class="inline-flex items-center rounded-xl bg-background px-3.5 py-2 text-[12px] font-bold text-text border border-border/60 transition-all duration-200 hover:bg-slate-100 hover:border-border active:scale-[0.97]">Reports</Link>
                </div>
            </div>

            <div v-if="dashboard.recentActivity.length" class="overflow-x-auto rounded-xl border border-border/60">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-background">
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Learner</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted">Activity</th>
                            <th class="px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(row, index) in dashboard.recentActivity" :key="index" class="group transition-colors duration-150 hover:bg-orange-50/40">
                            <td class="px-4 py-3 font-bold text-text group-hover:text-orange-600 transition-colors">{{ row.learner }}</td>
                            <td class="px-4 py-3 font-medium text-muted">{{ row.activity }}</td>
                            <td class="px-4 py-3 font-medium text-muted text-right">{{ row.date }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState v-else title="No recent activity" message="Learner diagnostic and module activity will appear here." />
        </DashboardCard>
    </TeacherLayout>
</template>

<style scoped>
.td-card-in { animation: td-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes td-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
