<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import AIServiceStatusBanner from '../../Components/AIServiceStatusBanner.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import {
    School,
    Users,
    GraduationCap,
    Activity,
    BookOpen,
    BarChart2,
    HeartPulse,
    ClipboardCheck,
    AlertTriangle,
    Inbox,
    Image,
    Video,
} from 'lucide-vue-next';

const props = defineProps({ dashboard: Object, aiService: Object, agentMediaMode: { type: String, default: 'chibi' } });
const page = usePage();
const mediaMode = ref(props.agentMediaMode === 'dynamic' ? 'dynamic' : 'chibi');
const savingMediaMode = ref(false);
const mediaModeLabel = computed(() => mediaMode.value === 'dynamic' ? 'Dynamic videos' : 'Chibi images');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const setMediaMode = async (mode) => {
    if (savingMediaMode.value || mediaMode.value === mode) return;

    savingMediaMode.value = true;
    try {
        const response = await fetch('/admin/agent-media-mode', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ mode }),
        });

        if (!response.ok) return;
        const payload = await response.json();
        mediaMode.value = payload.mode === 'dynamic' ? 'dynamic' : 'chibi';
        if (page.props.agentMedia) {
            page.props.agentMedia.mode = mediaMode.value;
        }
    } finally {
        savingMediaMode.value = false;
    }
};

const statusVariant = (status) => {
    if (!status) return 'primary';
    const s = String(status).toLowerCase();
    if (s === 'completed' || s === 'passed' || s === 'active') return 'success';
    if (s === 'failed' || s === 'inactive' || s === 'error') return 'danger';
    if (s === 'in_progress' || s === 'pending') return 'warning';
    return 'primary';
};
</script>

<template>
    <AdminLayout>

        <!-- ── Page header (Sociafy-style: clean, no gradient banner) ── -->
        <div class="mb-6">
            <h1 class="text-2xl font-extrabold text-text">Admin Dashboard</h1>
            <p class="mt-1 text-sm font-medium text-muted">Track performance and monitor the system across all schools and learners.</p>
        </div>

        <AIServiceStatusBanner
            :status="aiService"
            troubleshooting-href="/admin/system-monitoring"
            guide-href="/admin/ai-env-guide"
        />

        <!-- ── Stat cards ─────────────────────────────────── -->
        <DashboardCard class="mb-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-extrabold text-text">Agent Display Mode</h2>
                    <p class="mt-1 text-sm font-medium text-muted">Use lightweight chibi images for defense, or switch back to the dynamic talking videos.</p>
                </div>
                <div class="inline-grid grid-cols-2 gap-1 rounded-xl bg-background p-1 ring-1 ring-border/70">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-extrabold transition"
                        :class="mediaMode === 'chibi' ? 'bg-white text-primary shadow-sm' : 'text-muted hover:text-text'"
                        :disabled="savingMediaMode"
                        @click="setMediaMode('chibi')"
                    >
                        <Image :size="16" />
                        Chibi
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-extrabold transition"
                        :class="mediaMode === 'dynamic' ? 'bg-white text-primary shadow-sm' : 'text-muted hover:text-text'"
                        :disabled="savingMediaMode"
                        @click="setMediaMode('dynamic')"
                    >
                        <Video :size="16" />
                        Dynamic
                    </button>
                </div>
            </div>
            <p class="mt-3 text-xs font-bold text-muted">Current mode: {{ mediaModeLabel }}</p>
        </DashboardCard>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ScoreCard label="Total Schools"     :value="dashboard.counts.schools"          :icon="School"        color="blue"   />
            <ScoreCard label="Total Teachers"    :value="dashboard.counts.teachers"         :icon="Users"         color="green"  />
            <ScoreCard label="Total Learners"    :value="dashboard.counts.learners"         :icon="GraduationCap" color="purple" />
            <ScoreCard label="Sandbox Attempts"  :value="dashboard.counts.sandbox_attempts" :icon="Activity"      color="orange" />
        </div>

        <!-- ── Distribution + Health ─────────────────────── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-3">

            <!-- Learners by Module -->
            <DashboardCard>
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                            <BookOpen :size="15" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Learners by Module</h2>
                    </div>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(count, label) in dashboard.moduleDistribution"
                        :key="label"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors hover:bg-orange-50/60"
                    >
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <div v-if="!Object.keys(dashboard.moduleDistribution ?? {}).length" class="flex flex-col items-center gap-2 py-8 text-muted">
                        <Inbox :size="28" class="text-slate-300" />
                        <p class="text-sm font-medium">No module data yet.</p>
                    </div>
                </div>
            </DashboardCard>

            <!-- CRLA Levels -->
            <DashboardCard>
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 text-violet-500">
                            <BarChart2 :size="15" />
                        </div>
                        <h2 class="text-sm font-bold text-text">CRLA Levels</h2>
                    </div>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="(count, label) in dashboard.crlaDistribution"
                        :key="label"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 text-sm transition-colors hover:bg-violet-50/60"
                    >
                        <span class="font-semibold text-text">{{ label }}</span>
                        <StatusBadge :status="String(count)" />
                    </div>
                    <div v-if="!Object.keys(dashboard.crlaDistribution ?? {}).length" class="flex flex-col items-center gap-2 py-8 text-muted">
                        <Inbox :size="28" class="text-slate-300" />
                        <p class="text-sm font-medium">No CRLA data yet.</p>
                    </div>
                </div>
            </DashboardCard>

            <!-- System Health -->
            <DashboardCard>
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                            <HeartPulse :size="15" class="animate-pulse-soft" />
                        </div>
                        <h2 class="text-sm font-bold text-text">System Health</h2>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div
                        v-for="(value, key) in dashboard.systemHealth"
                        :key="key"
                        class="flex items-center justify-between rounded-xl bg-background px-3.5 py-2.5 transition-colors hover:bg-emerald-50/60"
                    >
                        <span class="font-semibold capitalize text-muted">{{ key }}</span>
                        <span class="font-bold text-text">{{ value }}</span>
                    </div>
                    <div v-if="!Object.keys(dashboard.systemHealth ?? {}).length" class="flex flex-col items-center gap-2 py-8 text-muted">
                        <HeartPulse :size="28" class="text-emerald-300" />
                        <p class="text-sm font-medium">All systems healthy.</p>
                    </div>
                </div>
            </DashboardCard>
        </div>

        <!-- ── Activity + Fallbacks ───────────────────────── -->
        <div class="mt-6 grid gap-4 lg:grid-cols-2">

            <!-- Recent Assessment Activity -->
            <DashboardCard>
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                            <ClipboardCheck :size="15" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Recent Assessment Activity</h2>
                    </div>
                </div>
                <div class="divide-y divide-border/60 text-sm">
                    <div
                        v-for="item in dashboard.recentAssessmentActivity"
                        :key="item.public_id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-50 text-orange-600 text-xs font-bold">
                                {{ (item.learner ?? '?').charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-text">{{ item.learner }}</p>
                                <p class="text-[11px] text-muted">{{ item.type }}</p>
                            </div>
                        </div>
                        <StatusBadge :status="item.status" :variant="statusVariant(item.status)" />
                    </div>
                    <div v-if="!dashboard.recentAssessmentActivity?.length" class="flex flex-col items-center gap-2 py-8 text-muted">
                        <Inbox :size="28" class="text-slate-300" />
                        <p class="text-sm font-medium">No recent activity yet.</p>
                        <p class="text-[11px]">Assessment events will show up here.</p>
                    </div>
                </div>
            </DashboardCard>

            <!-- Recent LLM Fallbacks -->
            <DashboardCard>
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                            <AlertTriangle :size="15" />
                        </div>
                        <h2 class="text-sm font-bold text-text">Recent LLM Fallbacks</h2>
                    </div>
                </div>
                <div class="divide-y divide-border/60 text-sm">
                    <div
                        v-for="item in dashboard.recentLlmFallbacks"
                        :key="item.public_id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-50 text-orange-500">
                                <AlertTriangle :size="13" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-text">{{ item.model }}</p>
                                <p class="text-[11px] text-muted">{{ item.created_at }}</p>
                            </div>
                        </div>
                        <StatusBadge :status="item.safety_status" variant="warning" />
                    </div>
                    <div v-if="!dashboard.recentLlmFallbacks?.length" class="flex flex-col items-center gap-2 py-8 text-muted">
                        <HeartPulse :size="28" class="text-emerald-300" />
                        <p class="text-sm font-medium">All systems happy!</p>
                        <p class="text-[11px]">No fallback events found.</p>
                    </div>
                </div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>
