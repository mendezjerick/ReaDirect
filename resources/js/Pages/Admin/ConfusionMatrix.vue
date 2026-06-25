<script setup>
import { computed, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import {
    AlertTriangle,
    BarChart3,
    CheckCircle,
    FileAudio,
    FlaskConical,
    Loader2,
    RefreshCw,
    Table2,
    XCircle,
} from 'lucide-vue-next';

const props = defineProps({
    manifest: Object,
    latestRun: Object,
    fixtureOptions: Array,
    routes: Object,
});

const mode = ref('automated');
const latestRun = ref(props.latestRun);
const fixtureOptions = ref(props.fixtureOptions ?? []);
const manualResult = ref(null);
const manualLoading = ref(false);
const refreshLoading = ref(false);
const manualForm = ref({
    category: fixtureOptions.value[0]?.key ?? '',
    task: fixtureOptions.value[0]?.tasks?.[0]?.key ?? '',
    item_key: fixtureOptions.value[0]?.tasks?.[0]?.items?.[0]?.key ?? '',
    fixture_type: fixtureOptions.value[0]?.tasks?.[0]?.items?.[0]?.fixtures?.[0]?.type ?? '',
});

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const overall = computed(() => latestRun.value?.summary?.overall ?? {});
const rows = computed(() => latestRun.value?.rows ?? []);
const criticalWrongRejects = computed(() => overall.value?.valid_audible_wrong_incorrectly_rejected ?? 0);
const categoryRows = computed(() => Object.values(latestRun.value?.summary?.by_category ?? {}));
const taskRows = computed(() => Object.values(latestRun.value?.summary?.by_task ?? {}));
const visibleRows = computed(() => rows.value.slice(0, 300));

const selectedCategory = computed(() => fixtureOptions.value.find(category => category.key === manualForm.value.category) ?? null);
const selectedTask = computed(() => selectedCategory.value?.tasks?.find(task => task.key === manualForm.value.task) ?? null);
const selectedItem = computed(() => selectedTask.value?.items?.find(item => item.key === manualForm.value.item_key) ?? null);
const selectedFixtures = computed(() => selectedItem.value?.fixtures ?? []);

const setMode = (value) => {
    mode.value = value;
};

const onCategoryChange = () => {
    const task = selectedCategory.value?.tasks?.[0];
    manualForm.value.task = task?.key ?? '';
    manualForm.value.item_key = task?.items?.[0]?.key ?? '';
    manualForm.value.fixture_type = task?.items?.[0]?.fixtures?.[0]?.type ?? '';
};

const onTaskChange = () => {
    const item = selectedTask.value?.items?.[0];
    manualForm.value.item_key = item?.key ?? '';
    manualForm.value.fixture_type = item?.fixtures?.[0]?.type ?? '';
};

const onItemChange = () => {
    manualForm.value.fixture_type = selectedFixtures.value[0]?.type ?? '';
};

const refreshResults = async () => {
    refreshLoading.value = true;
    try {
        const response = await fetch(props.routes.results, { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        latestRun.value = payload.latestRun;
    } finally {
        refreshLoading.value = false;
    }
};

const runManualFixture = async () => {
    manualLoading.value = true;
    manualResult.value = null;
    try {
        const response = await fetch(props.routes.runFixture, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(manualForm.value),
        });
        const payload = await response.json();
        manualResult.value = payload.result ?? payload;
    } finally {
        manualLoading.value = false;
    }
};

const pct = (value) => value === null || value === undefined ? '-' : `${Math.round(Number(value) * 1000) / 10}%`;
const metric = (value) => value === null || value === undefined ? '-' : value;

const resultVariant = (result) => {
    if (result === 'TP' || result === 'TN' || result === 'invalid_audio_rejected') return 'success';
    if (result === 'FP' || result === 'FN' || result === 'wrong_audio_rejected' || result === 'invalid_audio_accepted') return 'danger';
    return 'primary';
};
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Confusion Matrix</h1>
                <p class="mt-1 text-sm font-medium text-muted">ASR fixture results for recording validity and answer correctness.</p>
            </div>
            <div class="inline-flex rounded-xl border border-border/70 bg-surface p-1">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition-colors"
                    :class="mode === 'automated' ? 'bg-primary text-white' : 'text-slate-500 hover:bg-primary-light hover:text-primary'"
                    @click="setMode('automated')"
                >
                    <BarChart3 class="size-4" />
                    Automated
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition-colors"
                    :class="mode === 'manual' ? 'bg-primary text-white' : 'text-slate-500 hover:bg-primary-light hover:text-primary'"
                    @click="setMode('manual')"
                >
                    <FlaskConical class="size-4" />
                    Manual
                </button>
            </div>
        </div>

        <div v-if="mode === 'automated'">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <ScoreCard label="Tested Recordings" :value="latestRun?.total_tested_recordings ?? 0" :icon="FileAudio" color="blue" :subtitle="latestRun?.ran_at ?? 'No automated run yet'" />
                <ScoreCard label="TP / TN" :value="`${overall.TP ?? 0} / ${overall.TN ?? 0}`" :icon="CheckCircle" color="green" subtitle="correct accepts and wrong rejects" />
                <ScoreCard label="FP / FN" :value="`${overall.FP ?? 0} / ${overall.FN ?? 0}`" :icon="XCircle" color="orange" subtitle="answer-correctness failures" />
                <ScoreCard label="Wrong Audio Rejected" :value="criticalWrongRejects" :icon="AlertTriangle" color="orange" subtitle="valid audible files rejected as invalid" />
            </div>

            <DashboardCard class="mt-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-text">Latest Automated Run</h2>
                        <p class="mt-1 text-xs font-medium text-muted">Manifest: {{ manifest?.path }}</p>
                        <p class="mt-1 text-xs font-medium text-muted">Generated: {{ manifest?.generated_at ?? 'No manifest generated' }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-white px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:border-primary/30 hover:bg-primary-light hover:text-primary"
                        :disabled="refreshLoading"
                        @click="refreshResults"
                    >
                        <Loader2 v-if="refreshLoading" class="size-4 animate-spin" />
                        <RefreshCw v-else class="size-4" />
                        Refresh
                    </button>
                </div>

                <div v-if="!latestRun" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                    Run <span class="font-mono">php artisan asr:generate-fixtures</span>, then <span class="font-mono">php artisan asr:run-confusion-matrix</span>.
                </div>

                <div v-else class="mt-5 grid gap-3 md:grid-cols-4">
                    <div class="rounded-xl bg-background p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Accuracy</p>
                        <p class="mt-1 text-xl font-extrabold text-text">{{ pct(overall.accuracy) }}</p>
                    </div>
                    <div class="rounded-xl bg-background p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Precision</p>
                        <p class="mt-1 text-xl font-extrabold text-text">{{ pct(overall.precision) }}</p>
                    </div>
                    <div class="rounded-xl bg-background p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Recall</p>
                        <p class="mt-1 text-xl font-extrabold text-text">{{ pct(overall.recall) }}</p>
                    </div>
                    <div class="rounded-xl bg-background p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">F1</p>
                        <p class="mt-1 text-xl font-extrabold text-text">{{ pct(overall.f1) }}</p>
                    </div>
                </div>
            </DashboardCard>

            <DashboardCard v-if="latestRun" class="mt-5">
                <div class="mb-4 flex items-center gap-2">
                    <Table2 class="size-4 text-primary" />
                    <h2 class="text-sm font-bold text-text">Recording Validity Summary</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl bg-green-50 p-4 text-green-800">
                        <p class="text-[11px] font-bold uppercase tracking-wider">Valid Wrong Accepted</p>
                        <p class="mt-1 text-2xl font-extrabold">{{ overall.valid_audible_wrong_accepted ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-red-50 p-4 text-red-700">
                        <p class="text-[11px] font-bold uppercase tracking-wider">Valid Wrong Rejected</p>
                        <p class="mt-1 text-2xl font-extrabold">{{ overall.valid_audible_wrong_incorrectly_rejected ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl bg-background p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Silence / Low Volume Rejected</p>
                        <p class="mt-1 text-2xl font-extrabold text-text">{{ overall.silence_rejected ?? 0 }} / {{ overall.low_volume_rejected ?? 0 }}</p>
                    </div>
                </div>
            </DashboardCard>

            <div v-if="latestRun" class="mt-5 grid gap-5 xl:grid-cols-2">
                <DashboardCard>
                    <h2 class="mb-4 text-sm font-bold text-text">Per Category</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-[11px] uppercase tracking-wider text-muted">
                                <tr>
                                    <th class="py-2 pr-3">Category</th>
                                    <th class="py-2 pr-3">TP</th>
                                    <th class="py-2 pr-3">TN</th>
                                    <th class="py-2 pr-3">FP</th>
                                    <th class="py-2 pr-3">FN</th>
                                    <th class="py-2 pr-3">Wrong Rejects</th>
                                    <th class="py-2 pr-3">Accuracy</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="row in categoryRows" :key="row.key">
                                    <td class="py-2 pr-3 font-semibold text-text">{{ row.key }}</td>
                                    <td class="py-2 pr-3">{{ row.TP }}</td>
                                    <td class="py-2 pr-3">{{ row.TN }}</td>
                                    <td class="py-2 pr-3">{{ row.FP }}</td>
                                    <td class="py-2 pr-3">{{ row.FN }}</td>
                                    <td class="py-2 pr-3">{{ row.valid_audible_wrong_incorrectly_rejected }}</td>
                                    <td class="py-2 pr-3">{{ pct(row.accuracy) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DashboardCard>

                <DashboardCard>
                    <h2 class="mb-4 text-sm font-bold text-text">Per Task / Module</h2>
                    <div class="max-h-[360px] overflow-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="sticky top-0 bg-surface text-[11px] uppercase tracking-wider text-muted">
                                <tr>
                                    <th class="py-2 pr-3">Task</th>
                                    <th class="py-2 pr-3">TP</th>
                                    <th class="py-2 pr-3">TN</th>
                                    <th class="py-2 pr-3">FP</th>
                                    <th class="py-2 pr-3">FN</th>
                                    <th class="py-2 pr-3">Wrong Rejects</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="row in taskRows" :key="row.key">
                                    <td class="py-2 pr-3 font-semibold text-text">{{ row.key }}</td>
                                    <td class="py-2 pr-3">{{ row.TP }}</td>
                                    <td class="py-2 pr-3">{{ row.TN }}</td>
                                    <td class="py-2 pr-3">{{ row.FP }}</td>
                                    <td class="py-2 pr-3">{{ row.FN }}</td>
                                    <td class="py-2 pr-3">{{ row.valid_audible_wrong_incorrectly_rejected }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </DashboardCard>
            </div>

            <DashboardCard v-if="latestRun" class="mt-5">
                <h2 class="mb-4 text-sm font-bold text-text">Fixture Results</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-[1400px] text-left text-xs">
                        <thead class="text-[10px] uppercase tracking-wider text-muted">
                            <tr>
                                <th class="py-2 pr-3">Category</th>
                                <th class="py-2 pr-3">Task</th>
                                <th class="py-2 pr-3">Item</th>
                                <th class="py-2 pr-3">Expected</th>
                                <th class="py-2 pr-3">Fixture</th>
                                <th class="py-2 pr-3">Spoken</th>
                                <th class="py-2 pr-3">Audio</th>
                                <th class="py-2 pr-3">Recording Accepted?</th>
                                <th class="py-2 pr-3">Raw ASR</th>
                                <th class="py-2 pr-3">Normalized</th>
                                <th class="py-2 pr-3">Final Correct?</th>
                                <th class="py-2 pr-3">Expected Correct?</th>
                                <th class="py-2 pr-3">Result</th>
                                <th class="py-2 pr-3">Failure</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="row in visibleRows" :key="`${row.category}-${row.task}-${row.item_key}-${row.fixture_type}`">
                                <td class="py-2 pr-3 font-semibold">{{ row.category }}</td>
                                <td class="py-2 pr-3">{{ row.task }}</td>
                                <td class="py-2 pr-3">{{ row.item_key }}</td>
                                <td class="max-w-[180px] truncate py-2 pr-3">{{ row.expected_answer }}</td>
                                <td class="py-2 pr-3">{{ row.fixture_type }}</td>
                                <td class="max-w-[180px] truncate py-2 pr-3">{{ row.spoken_text || '-' }}</td>
                                <td class="max-w-[220px] truncate py-2 pr-3 font-mono">{{ row.audio_file_path }}</td>
                                <td class="py-2 pr-3">{{ row.recording_accepted ? 'Yes' : 'No' }}</td>
                                <td class="max-w-[180px] truncate py-2 pr-3">{{ row.asr_raw_output || '-' }}</td>
                                <td class="max-w-[180px] truncate py-2 pr-3">{{ row.normalized_output || '-' }}</td>
                                <td class="py-2 pr-3">{{ row.final_correctness_result ? 'Yes' : 'No' }}</td>
                                <td class="py-2 pr-3">{{ row.expected_correctness ? 'Yes' : 'No' }}</td>
                                <td class="py-2 pr-3"><StatusBadge :status="row.confusion_matrix_result" :variant="resultVariant(row.confusion_matrix_result)" /></td>
                                <td class="max-w-[240px] truncate py-2 pr-3 text-red-600">{{ row.failure_reason || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="rows.length > visibleRows.length" class="mt-3 text-xs font-semibold text-muted">Showing first {{ visibleRows.length }} of {{ rows.length }} rows.</p>
            </DashboardCard>
        </div>

        <div v-else>
            <DashboardCard>
                <h2 class="text-sm font-bold text-text">Manual Fixture Test</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Category</span>
                        <select v-model="manualForm.category" class="rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold" @change="onCategoryChange">
                            <option v-for="category in fixtureOptions" :key="category.key" :value="category.key">{{ category.label }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Task / Module</span>
                        <select v-model="manualForm.task" class="rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold" @change="onTaskChange">
                            <option v-for="task in selectedCategory?.tasks ?? []" :key="task.key" :value="task.key">{{ task.label }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Item</span>
                        <select v-model="manualForm.item_key" class="rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold" @change="onItemChange">
                            <option v-for="item in selectedTask?.items ?? []" :key="item.key" :value="item.key">{{ item.label }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Audio Fixture</span>
                        <select v-model="manualForm.fixture_type" class="rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold">
                            <option v-for="fixture in selectedFixtures" :key="fixture.type" :value="fixture.type">{{ fixture.label }}</option>
                        </select>
                    </label>
                </div>
                <button
                    type="button"
                    class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-dark disabled:opacity-60"
                    :disabled="manualLoading || !manualForm.fixture_type"
                    @click="runManualFixture"
                >
                    <Loader2 v-if="manualLoading" class="size-4 animate-spin" />
                    <FlaskConical v-else class="size-4" />
                    Run ASR Test
                </button>
            </DashboardCard>

            <DashboardCard v-if="manualResult" class="mt-5">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <h2 class="mr-2 text-sm font-bold text-text">Manual Result</h2>
                    <StatusBadge :status="manualResult.confusion_matrix_result" :variant="resultVariant(manualResult.confusion_matrix_result)" />
                    <StatusBadge :status="manualResult.recording_accepted ? 'recording accepted' : 'recording rejected'" :variant="manualResult.recording_accepted ? 'success' : 'danger'" />
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-2 text-sm">
                        <p><span class="font-bold text-muted">Expected:</span> {{ manualResult.expected_answer }}</p>
                        <p><span class="font-bold text-muted">Spoken:</span> {{ manualResult.spoken_text || '-' }}</p>
                        <p><span class="font-bold text-muted">Raw ASR:</span> {{ manualResult.asr_raw_output || '-' }}</p>
                        <p><span class="font-bold text-muted">Normalized:</span> {{ manualResult.normalized_output || '-' }}</p>
                        <p><span class="font-bold text-muted">Final correctness:</span> {{ manualResult.final_correctness_result ? 'Correct' : 'Incorrect' }}</p>
                        <p><span class="font-bold text-muted">Failure:</span> <span class="text-red-600">{{ manualResult.failure_reason || '-' }}</span></p>
                    </div>
                    <div class="grid gap-2 rounded-xl bg-background p-4 text-sm">
                        <p><span class="font-bold text-muted">True GOP score:</span> {{ metric(manualResult.scoring_debug?.true_gop_score) }}</p>
                        <p><span class="font-bold text-muted">GOP decision:</span> {{ metric(manualResult.scoring_debug?.gop_decision) }}</p>
                        <p><span class="font-bold text-muted">Beam search:</span> {{ metric(manualResult.scoring_debug?.beam_search) }}</p>
                        <p><span class="font-bold text-muted">Expected-centric score:</span> {{ metric(manualResult.scoring_debug?.expected_centric_score) }}</p>
                        <p><span class="font-bold text-muted">Threshold:</span> {{ metric(manualResult.scoring_debug?.threshold_used) }}</p>
                        <p><span class="font-bold text-muted">Correction strategy:</span> {{ metric(manualResult.scoring_debug?.correction_strategy_used) }}</p>
                    </div>
                </div>
                <details class="mt-4 rounded-xl border border-border bg-white p-4">
                    <summary class="cursor-pointer text-sm font-bold text-text">Raw result JSON</summary>
                    <pre class="mt-3 max-h-[420px] overflow-auto text-xs">{{ JSON.stringify(manualResult, null, 2) }}</pre>
                </details>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>
