<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import {
    ArrowLeft,
    ArrowRight,
    ExternalLink,
    GraduationCap,
    Loader2,
    Navigation,
    Play,
    Rocket,
} from 'lucide-vue-next';

const props = defineProps({ learner: Object, targets: Array, modules: Array });

const form = useForm({
    learner_id: props.learner?.id ?? '',
    type: 'diagnostic',
    module_id: props.modules?.[0]?.id ?? '',
});

const typeLabels = {
    diagnostic: { label: 'Sandbox Diagnostic', color: 'blue' },
    module: { label: 'Sandbox Module', color: 'violet' },
    final: { label: 'Sandbox Final Reassessment', color: 'orange' },
};
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Flow Jump</h1>
                <p class="mt-1 text-sm font-medium text-muted">
                    Jump links prepare the needed sandbox/session state before opening learner pages.
                </p>
            </div>
            <Link href="/admin/testing/learners" class="group inline-flex shrink-0 w-full md:w-auto items-center justify-center gap-2 rounded-xl bg-background px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:shadow-sm">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to Learner Select
            </Link>
        </div>

        <!-- ── Learner + Create Attempt ────────────────────── -->
        <DashboardCard class="fj-card-in">
            <div class="mb-4 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                    <GraduationCap class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Sandbox Session</h2>
            </div>

            <!-- Current learner info -->
            <div class="flex items-center gap-3 rounded-xl bg-background px-4 py-3 mb-5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600 text-sm font-bold">
                    {{ learner ? (learner.first_name ?? learner.learner_code ?? '?').charAt(0).toUpperCase() : '?' }}
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Current learner</p>
                    <p v-if="learner" class="text-sm font-semibold text-text truncate">
                        <span class="font-bold text-primary">{{ learner.learner_code }}</span>
                        <span class="text-muted font-medium mx-1">&mdash;</span>
                        {{ learner.first_name }} {{ learner.last_name }}
                    </p>
                    <p v-else class="text-sm font-medium text-muted">None selected</p>
                </div>
            </div>

            <!-- Create attempt form -->
            <form v-if="learner" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="form.post('/admin/testing/start-sandbox')">
                <input v-model="form.learner_id" type="hidden">

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Attempt type</span>
                    <select v-model="form.type" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option value="diagnostic">Sandbox diagnostic</option>
                        <option value="module">Sandbox module</option>
                        <option value="final">Sandbox final reassessment</option>
                    </select>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Module</span>
                    <select v-model="form.module_id" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option>
                    </select>
                </label>

                <div class="flex items-end sm:col-span-2">
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                        <Rocket v-else class="size-4" />
                        Create Sandbox Attempt
                    </button>
                </div>
            </form>

            <div v-else class="rounded-xl bg-amber-50 border border-amber-200/60 px-4 py-3 text-sm font-semibold text-amber-700">
                No learner selected. Go back and choose a learner to continue.
            </div>
        </DashboardCard>

        <!-- ── Jump Targets ────────────────────────────────── -->
        <DashboardCard class="mt-5 fj-card-in" style="--card-delay: 100ms">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                        <Navigation class="size-4" />
                    </div>
                    <h2 class="text-sm font-bold text-text">Jump Targets</h2>
                </div>
            </div>

            <EmptyState
                v-if="!targets?.length"
                title="No jump targets"
                message="Create a sandbox attempt first to generate jump targets."
            />

            <div v-else class="grid gap-2 sm:grid-cols-2">
                <a
                    v-for="(target, index) in targets"
                    :key="target.url"
                    :href="target.url"
                    class="group flex items-center gap-3 rounded-xl border border-border/60 bg-background/50 px-4 py-3 transition-all duration-200 hover:bg-primary-light hover:border-primary/20 hover:shadow-sm active:scale-[0.98] fj-row-in"
                    :style="{ '--row-delay': `${index * 50}ms` }"
                >
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/5 text-primary transition-colors group-hover:bg-primary/10">
                        <Play class="size-3.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-muted">{{ target.group }}</p>
                        <p class="text-sm font-semibold text-text group-hover:text-primary transition-colors truncate">{{ target.label }}</p>
                    </div>
                    <ExternalLink class="size-4 shrink-0 text-slate-300 transition-all duration-200 group-hover:text-primary group-hover:translate-x-0.5" />
                </a>
            </div>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
/* ─── Staggered card entrance ─────────────────────────── */
.fj-card-in {
    animation: fj-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}

@keyframes fj-card-entrance {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ─── Staggered row entrance ──────────────────────────── */
.fj-row-in {
    animation: fj-row-entrance 350ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--row-delay, 0ms);
}

@keyframes fj-row-entrance {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
