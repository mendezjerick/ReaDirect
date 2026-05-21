<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { ArrowLeft, Loader2, Save, Settings } from 'lucide-vue-next';

const props = defineProps({ rule: Object });
const form = useForm({
    min_score: props.rule.min_score,
    max_score: props.rule.max_score,
    decision: props.rule.decision,
    next_module_key: props.rule.next_module_key,
    rule_key: props.rule.rule_key,
});
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Edit Rule</h1>
                <p class="mt-1 text-sm font-medium text-muted">Editing {{ rule.rule_key }}</p>
            </div>
            <Link :href="`/admin/rules/${rule.id}`" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to rule
            </Link>
        </div>

        <DashboardCard class="rf-card-in">
            <div class="mb-5 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <Settings class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Rule Settings</h2>
            </div>

            <form class="grid gap-5" @submit.prevent="form.put(`/admin/rules/${rule.id}`)">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Min Score</span>
                        <input v-model="form.min_score" type="number" step="0.01" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.min_score" class="text-[11px] font-semibold text-red-500">{{ form.errors.min_score }}</p>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Max Score</span>
                        <input v-model="form.max_score" type="number" step="0.01" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.max_score" class="text-[11px] font-semibold text-red-500">{{ form.errors.max_score }}</p>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Decision</span>
                        <input v-model="form.decision" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.decision" class="text-[11px] font-semibold text-red-500">{{ form.errors.decision }}</p>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Next Module Key</span>
                        <input v-model="form.next_module_key" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.next_module_key" class="text-[11px] font-semibold text-red-500">{{ form.errors.next_module_key }}</p>
                    </label>
                </div>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Rule Key</span>
                    <input v-model="form.rule_key" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                    <p v-if="form.errors.rule_key" class="text-[11px] font-semibold text-red-500">{{ form.errors.rule_key }}</p>
                </label>

                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Save Changes
                </button>
            </form>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.rf-card-in { animation: rf-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes rf-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
