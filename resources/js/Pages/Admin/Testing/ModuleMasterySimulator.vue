<script setup>
import { computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import {
    ArrowRight,
    BookOpen,
    Calculator,
    FlaskConical,
    RotateCcw,
    ShieldCheck,
    SlidersHorizontal,
} from 'lucide-vue-next';

const props = defineProps({
    learner: Object,
    result: Object,
    defaults: Object,
    routes: Object,
});

const form = useForm({
    task_1_score: props.result?.inputs?.task_1_score ?? props.defaults.task_1_score,
    task_2_score: props.result?.inputs?.task_2_score_entered ?? props.defaults.task_2_score,
    task_3_score: props.result?.inputs?.task_3_score ?? props.defaults.task_3_score,
    incorrect_words: props.result?.inputs?.incorrect_words ?? props.defaults.incorrect_words,
    comprehension_correct_count: props.result?.inputs?.comprehension_correct_count ?? props.defaults.comprehension_correct_count,
});

const running = computed(() => form.processing);
const finalModuleLabel = computed(() => props.result?.module?.title ?? 'No module assigned');
const finalModuleKey = computed(() => props.result?.module?.key ?? 'grade_ready');

const submit = () => {
    form.post(props.routes.simulate, {
        preserveScroll: true,
    });
};

const resetMm = () => {
    router.post(props.routes.reset, {}, {
        preserveScroll: true,
        onSuccess: () => form.defaults(props.defaults).reset(),
    });
};

const scoreRows = computed(() => {
    if (!props.result) return [];

    return [
        ['Task 1 entered', `${props.result.inputs.task_1_score} / 10`],
        ['Task 2A entered', `${props.result.inputs.task_2_score_entered} / 10`],
        ['Task 2A effective', `${props.result.computed.effective_task_2_score} / 10`],
        ['Task 2B entered', `${props.result.inputs.task_3_score} / 10`],
        ['CRLA total', `${props.result.computed.crla_total_score} / 30`],
        ['CRLA percentage', `${props.result.computed.crla_percentage}%`],
        ['CRLA classification', props.result.computed.crla_classification],
    ];
});

const readingRows = computed(() => {
    if (!props.result) return [];

    return [
        ['Incorrect words', props.result.inputs.incorrect_words],
        ['Passage accuracy', `${props.result.computed.reading_accuracy}%`],
        ['Comprehension', `${props.result.inputs.comprehension_correct_count} / ${props.result.inputs.comprehension_total}`],
        ['Comprehension percentage', `${props.result.computed.comprehension_percentage}%`],
        ['Reading weighting', props.result.computed.reading_weighting],
        ['Final reading score', `${props.result.computed.final_reading_score}%`],
        ['Reading classification', props.result.computed.reading_classification],
    ];
});

const weightRows = computed(() => {
    const weights = props.result?.computed?.weight_calculation;
    if (!weights) return [];

    return [
        [
            'Comprehension',
            `${weights.comprehension_percentage}%`,
            `x ${weights.comprehension_weight}`,
            `${weights.comprehension_contribution}`,
        ],
        [
            'Passage accuracy',
            `${weights.accuracy_percentage}%`,
            `x ${weights.accuracy_weight}`,
            `${weights.accuracy_contribution}`,
        ],
        [
            'Final reading score',
            weights.formula,
            '=',
            `${weights.sum}%`,
        ],
    ];
});

const ruleTables = computed(() => props.result?.rule_tables ?? []);
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                        <FlaskConical class="size-4" />
                    </span>
                    <h1 class="text-2xl font-extrabold text-text">Module Mastery Simulator</h1>
                </div>
                <p class="max-w-3xl text-sm font-medium leading-relaxed text-muted">
                    Admin-only diagnostic placement simulator using the real CRLA, reading, and module placement services.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link href="/admin/testing/true-sandbox" class="inline-flex items-center justify-center gap-2 rounded-xl border border-border/60 bg-surface px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all hover:border-primary/30 hover:bg-primary-light hover:text-primary">
                    True Sandbox
                    <ArrowRight class="size-4" />
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-[13px] font-bold text-rose-600 transition-all hover:bg-rose-100 active:scale-[0.97]"
                    @click="resetMm"
                >
                    <RotateCcw class="size-4" />
                    Reset MM
                </button>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,420px)_1fr]">
            <DashboardCard>
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Simulator learner</p>
                        <h2 class="mt-1 text-lg font-extrabold text-text">{{ learner.name }}</h2>
                        <p class="mt-1 text-[12px] font-semibold text-muted">{{ learner.learner_code }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700">
                        <ShieldCheck class="size-3.5" />
                        Admin only
                    </span>
                </div>

                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-3">
                        <div class="flex items-center gap-2">
                            <SlidersHorizontal class="size-4 text-primary" />
                            <h3 class="text-sm font-bold text-text">Diagnostic Scores</h3>
                        </div>

                        <label v-for="field in [
                            ['task_1_score', 'Task 1', 10],
                            ['task_2_score', 'Task 2A', 10],
                            ['task_3_score', 'Task 2B', 10],
                        ]" :key="field[0]" class="grid gap-2">
                            <span class="flex items-center justify-between text-[12px] font-bold text-slate-500">
                                {{ field[1] }}
                                <span class="text-text">{{ form[field[0]] }} / {{ field[2] }}</span>
                            </span>
                            <div class="flex items-center gap-3">
                                <input v-model.number="form[field[0]]" type="range" min="0" :max="field[2]" step="1" class="w-full accent-primary">
                                <input v-model.number="form[field[0]]" type="number" min="0" :max="field[2]" class="h-10 w-20 rounded-xl border border-border bg-white px-3 text-sm font-bold text-text focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </div>
                            <p v-if="form.errors[field[0]]" class="text-xs font-semibold text-rose-600">{{ form.errors[field[0]] }}</p>
                        </label>
                    </div>

                    <div class="grid gap-3 border-t border-border/60 pt-4">
                        <div class="flex items-center gap-2">
                            <BookOpen class="size-4 text-primary" />
                            <h3 class="text-sm font-bold text-text">Reading Score</h3>
                        </div>

                        <label class="grid gap-2">
                            <span class="flex items-center justify-between text-[12px] font-bold text-slate-500">
                                Passage incorrect words
                                <span class="text-text">{{ form.incorrect_words }} / 50</span>
                            </span>
                            <div class="flex items-center gap-3">
                                <input v-model.number="form.incorrect_words" type="range" min="0" max="50" step="1" class="w-full accent-primary">
                                <input v-model.number="form.incorrect_words" type="number" min="0" max="50" class="h-10 w-20 rounded-xl border border-border bg-white px-3 text-sm font-bold text-text focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </div>
                            <p v-if="form.errors.incorrect_words" class="text-xs font-semibold text-rose-600">{{ form.errors.incorrect_words }}</p>
                        </label>

                        <label class="grid gap-2">
                            <span class="flex items-center justify-between text-[12px] font-bold text-slate-500">
                                Comprehension correct
                                <span class="text-text">{{ form.comprehension_correct_count }} / 5</span>
                            </span>
                            <div class="flex items-center gap-3">
                                <input v-model.number="form.comprehension_correct_count" type="range" min="0" max="5" step="1" class="w-full accent-primary">
                                <input v-model.number="form.comprehension_correct_count" type="number" min="0" max="5" class="h-10 w-20 rounded-xl border border-border bg-white px-3 text-sm font-bold text-text focus:border-primary focus:ring-2 focus:ring-primary/10">
                            </div>
                            <p v-if="form.errors.comprehension_correct_count" class="text-xs font-semibold text-rose-600">{{ form.errors.comprehension_correct_count }}</p>
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white transition-all hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="running"
                    >
                        <Calculator class="size-4" />
                        Run Simulation
                    </button>
                </form>
            </DashboardCard>

            <div class="grid gap-5">
                <DashboardCard>
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Placement result</p>
                            <h2 class="mt-1 text-xl font-extrabold text-text">{{ finalModuleLabel }}</h2>
                        </div>
                        <StatusBadge :status="finalModuleKey" />
                    </div>

                    <div v-if="!result" class="rounded-xl border border-dashed border-border bg-background px-4 py-8 text-center">
                        <p class="text-sm font-semibold text-muted">No simulation result yet.</p>
                    </div>

                    <div v-else class="grid gap-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl bg-background p-4">
                                <h3 class="mb-3 text-sm font-bold text-text">Diagnostic Breakdown</h3>
                                <dl class="grid gap-2">
                                    <div v-for="row in scoreRows" :key="row[0]" class="flex items-start justify-between gap-3 text-sm">
                                        <dt class="font-semibold text-muted">{{ row[0] }}</dt>
                                        <dd class="text-right font-bold text-text">{{ row[1] }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="rounded-xl bg-background p-4">
                                <h3 class="mb-3 text-sm font-bold text-text">Reading Breakdown</h3>
                                <dl class="grid gap-2">
                                    <div v-for="row in readingRows" :key="row[0]" class="flex items-start justify-between gap-3 text-sm">
                                        <dt class="font-semibold text-muted">{{ row[0] }}</dt>
                                        <dd class="max-w-[220px] text-right font-bold text-text">{{ row[1] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="rounded-xl bg-background p-4">
                            <h3 class="mb-3 text-sm font-bold text-text">Reading Weight Calculation</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[560px] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-border/70 text-[11px] uppercase tracking-wider text-muted">
                                            <th class="py-2 pr-3 font-bold">Component</th>
                                            <th class="px-3 py-2 font-bold">Score</th>
                                            <th class="px-3 py-2 font-bold">Weight</th>
                                            <th class="py-2 pl-3 text-right font-bold">Contribution</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/50">
                                        <tr v-for="row in weightRows" :key="row[0]">
                                            <td class="py-2 pr-3 font-semibold text-text">{{ row[0] }}</td>
                                            <td class="px-3 py-2 font-medium text-slate-600">{{ row[1] }}</td>
                                            <td class="px-3 py-2 font-medium text-slate-600">{{ row[2] }}</td>
                                            <td class="py-2 pl-3 text-right font-extrabold text-text">{{ row[3] }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="rounded-xl bg-sky-50 p-4 ring-1 ring-sky-100">
                            <h3 class="mb-3 text-sm font-bold text-sky-900">Matched Rules</h3>
                            <div class="grid gap-3 text-sm">
                                <p class="font-semibold text-sky-800">
                                    <span class="font-extrabold">{{ result.rules.crla_routing }}:</span>
                                    {{ result.task_routing.condition }}
                                </p>
                                <p class="font-semibold text-sky-800">
                                    <span class="font-extrabold">{{ result.rules.crla_classification.rule_applied }}:</span>
                                    {{ result.rules.crla_classification.condition }}
                                </p>
                                <p class="font-semibold text-sky-800">
                                    <span class="font-extrabold">{{ result.rules.reading_classification.rule_applied }}:</span>
                                    {{ result.rules.reading_classification.condition }}
                                </p>
                                <p class="font-semibold text-sky-800">
                                    <span class="font-extrabold">{{ result.rules.module_placement.rule_applied }}:</span>
                                    {{ result.rules.module_placement.matched_condition }}
                                </p>
                            </div>
                        </div>

                        <div class="rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                            <p class="text-sm font-extrabold text-emerald-900">{{ result.rules.module_placement.decision }}</p>
                            <p class="mt-2 text-sm font-semibold leading-relaxed text-emerald-800">{{ result.rules.module_placement.decision_reason }}</p>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-emerald-700">{{ result.rules.module_placement.placement_explanation }}</p>
                        </div>

                        <div class="grid gap-4">
                            <section v-for="table in ruleTables" :key="table.title" class="rounded-xl border border-border/60 bg-white p-4">
                                <h3 class="mb-3 text-sm font-bold text-text">{{ table.title }}</h3>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[640px] text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-border/70 text-[11px] uppercase tracking-wider text-muted">
                                                <th v-for="column in table.columns" :key="column" class="px-3 py-2 first:pl-0 last:pr-0 font-bold">
                                                    {{ column }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border/50">
                                            <tr v-for="(row, rowIndex) in table.rows" :key="`${table.title}-${rowIndex}`">
                                                <td v-for="(cell, cellIndex) in row" :key="`${table.title}-${rowIndex}-${cellIndex}`" class="px-3 py-2 first:pl-0 last:pr-0 font-semibold text-slate-600">
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </div>
                </DashboardCard>
            </div>
        </div>
    </AdminLayout>
</template>
