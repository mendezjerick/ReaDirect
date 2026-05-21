<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import {
    ArrowLeft,
    Loader2,
    Pencil,
    Rocket,
    Settings,
} from 'lucide-vue-next';

const props = defineProps({ prompt: Object, agents: Array });
const isEditing = !!props.prompt;

const form = useForm({
    agent_profile_id: props.prompt?.agent_profile_id ?? '',
    key: props.prompt?.key ?? '',
    version: props.prompt?.version ?? 1,
    status: props.prompt?.status ?? 'draft',
    template: props.prompt?.template ?? '',
    variables: props.prompt?.variables ? JSON.stringify(props.prompt.variables, null, 2) : '',
});

const submit = () => isEditing ? form.put(`/admin/prompts/${props.prompt.id}`) : form.post('/admin/prompts');
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">{{ isEditing ? 'Edit Prompt' : 'Create Prompt' }}</h1>
                <p class="mt-1 text-sm font-medium text-muted">
                    {{ isEditing ? `Editing ${prompt.key} (v${prompt.version})` : 'Create a new prompt template for an agent.' }}
                </p>
            </div>
            <Link
                :href="isEditing ? `/admin/prompts/${prompt.id}` : '/admin/prompts'"
                class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]"
            >
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                {{ isEditing ? 'Back to prompt' : 'Back to prompts' }}
            </Link>
        </div>

        <!-- ── Form card ───────────────────────────────────── -->
        <DashboardCard class="pf-card-in">
            <div class="mb-5 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="isEditing ? 'bg-orange-50 text-orange-500' : 'bg-blue-50 text-blue-500'">
                    <Settings v-if="isEditing" class="size-4" />
                    <Pencil v-else class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">{{ isEditing ? 'Prompt Settings' : 'New Prompt' }}</h2>
            </div>

            <form class="grid gap-5" @submit.prevent="submit">
                <!-- Agent + Key -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Agent</span>
                        <select v-model="form.agent_profile_id" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option value="">Select agent</option>
                            <option v-for="agent in agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
                        </select>
                        <p v-if="form.errors.agent_profile_id" class="text-[11px] font-semibold text-red-500">{{ form.errors.agent_profile_id }}</p>
                    </label>

                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Key</span>
                        <input v-model="form.key" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="e.g. coach_feedback_correct">
                        <p v-if="form.errors.key" class="text-[11px] font-semibold text-red-500">{{ form.errors.key }}</p>
                    </label>
                </div>

                <!-- Version + Status -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Version</span>
                        <input v-model="form.version" type="number" min="1" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.version" class="text-[11px] font-semibold text-red-500">{{ form.errors.version }}</p>
                    </label>

                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Status</span>
                        <select v-model="form.status" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                            <option>draft</option>
                            <option>active</option>
                            <option>inactive</option>
                        </select>
                        <p v-if="form.errors.status" class="text-[11px] font-semibold text-red-500">{{ form.errors.status }}</p>
                    </label>
                </div>

                <!-- Template -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Template</span>
                    <textarea v-model="form.template" class="min-h-[200px] w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-mono font-medium text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="Enter the prompt template content…" />
                    <p v-if="form.errors.template" class="text-[11px] font-semibold text-red-500">{{ form.errors.template }}</p>
                </label>

                <!-- Variables JSON -->
                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Variables JSON</span>
                    <textarea v-model="form.variables" class="min-h-[120px] w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-mono font-medium text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder='{ "variable_name": "description" }' />
                    <p v-if="form.errors.variables" class="text-[11px] font-semibold text-red-500">{{ form.errors.variables }}</p>
                </label>

                <!-- Submit -->
                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    <Rocket v-else class="size-4" />
                    {{ isEditing ? 'Update Prompt' : 'Create Prompt' }}
                </button>
            </form>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.pf-card-in {
    animation: pf-card-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--card-delay, 0ms);
}
@keyframes pf-card-entrance {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
