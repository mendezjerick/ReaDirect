<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({ dashboard: Object });
</script>

<template>
    <AdminLayout>
        <div class="mb-6">
            <h1 class="text-3xl font-black text-text">Admin Dashboard</h1>
            <p class="mt-1 text-sm font-semibold text-muted">System-wide operational overview.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <ScoreCard label="Schools" :value="dashboard.counts.schools" />
            <ScoreCard label="Teachers" :value="dashboard.counts.teachers" />
            <ScoreCard label="Learners" :value="dashboard.counts.learners" />
            <ScoreCard label="Sandbox attempts" :value="dashboard.counts.sandbox_attempts" />
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <DashboardCard>
                <h2 class="font-black text-text">Learners by Module</h2>
                <div class="mt-3 grid gap-2">
                    <div v-for="(count, label) in dashboard.moduleDistribution" :key="label" class="flex justify-between rounded-xl bg-background px-3 py-2">
                        <span>{{ label }}</span><StatusBadge :status="String(count)" />
                    </div>
                </div>
            </DashboardCard>
            <DashboardCard>
                <h2 class="font-black text-text">CRLA Levels</h2>
                <div class="mt-3 grid gap-2">
                    <div v-for="(count, label) in dashboard.crlaDistribution" :key="label" class="flex justify-between rounded-xl bg-background px-3 py-2">
                        <span>{{ label }}</span><StatusBadge :status="String(count)" />
                    </div>
                </div>
            </DashboardCard>
            <DashboardCard>
                <h2 class="font-black text-text">System Health</h2>
                <div class="mt-3 grid gap-2 text-sm">
                    <div v-for="(value, key) in dashboard.systemHealth" :key="key" class="flex justify-between rounded-xl bg-background px-3 py-2">
                        <span class="font-bold text-muted">{{ key }}</span><span>{{ value }}</span>
                    </div>
                </div>
            </DashboardCard>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <DashboardCard>
                <h2 class="font-black text-text">Recent Assessment Activity</h2>
                <div class="mt-3 divide-y divide-border text-sm">
                    <div v-for="item in dashboard.recentAssessmentActivity" :key="item.public_id" class="py-2">
                        <strong>{{ item.learner }}</strong> - {{ item.type }} - {{ item.status }}
                    </div>
                </div>
            </DashboardCard>
            <DashboardCard>
                <h2 class="font-black text-text">Recent LLM Fallbacks</h2>
                <div class="mt-3 divide-y divide-border text-sm">
                    <div v-for="item in dashboard.recentLlmFallbacks" :key="item.public_id" class="py-2">
                        {{ item.model }} - {{ item.safety_status }} - {{ item.created_at }}
                    </div>
                    <p v-if="!dashboard.recentLlmFallbacks?.length" class="text-muted">No fallback events found.</p>
                </div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>
