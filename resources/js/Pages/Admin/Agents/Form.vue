<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { ArrowLeft, Loader2, Save, Settings } from 'lucide-vue-next';

const props = defineProps({ agent: Object });
const form = useForm({
    name: props.agent.name,
    purpose: props.agent.purpose,
    sprite_path: props.agent.sprite_path ?? '',
    default_state: props.agent.default_state ?? 'idle',
    voice_settings: props.agent.voice_settings ? JSON.stringify(props.agent.voice_settings, null, 2) : '',
    is_active: props.agent.is_active ?? true,
});
</script>

<template>
    <AdminLayout>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Edit Agent</h1>
                <p class="mt-1 text-sm font-medium text-muted">Editing {{ agent.name }}</p>
            </div>
            <Link :href="`/admin/agents/${agent.id}`" class="group inline-flex shrink-0 w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-background border border-border/60 px-4 py-2.5 text-[13px] font-semibold text-slate-500 transition-all duration-200 hover:bg-primary-light hover:text-primary hover:border-primary/30 active:scale-[0.97]">
                <ArrowLeft class="size-4 transition-transform duration-200 group-hover:-translate-x-0.5" />
                Back to agent
            </Link>
        </div>

        <DashboardCard class="af-card-in">
            <div class="mb-5 flex items-center gap-2.5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <Settings class="size-4" />
                </div>
                <h2 class="text-sm font-bold text-text">Agent Settings</h2>
            </div>

            <form class="grid gap-5" @submit.prevent="form.put(`/admin/agents/${agent.id}`)">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Name</span>
                        <input v-model="form.name" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.name" class="text-[11px] font-semibold text-red-500">{{ form.errors.name }}</p>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Default State</span>
                        <input v-model="form.default_state" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10">
                        <p v-if="form.errors.default_state" class="text-[11px] font-semibold text-red-500">{{ form.errors.default_state }}</p>
                    </label>
                </div>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Purpose</span>
                    <textarea v-model="form.purpose" class="min-h-[100px] w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-medium text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" />
                    <p v-if="form.errors.purpose" class="text-[11px] font-semibold text-red-500">{{ form.errors.purpose }}</p>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Sprite Path</span>
                    <input v-model="form.sprite_path" class="w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-semibold text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder="/images/agents/...">
                    <p v-if="form.errors.sprite_path" class="text-[11px] font-semibold text-red-500">{{ form.errors.sprite_path }}</p>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Voice Settings (JSON)</span>
                    <textarea v-model="form.voice_settings" class="min-h-[120px] w-full rounded-xl border border-border bg-white px-3 py-2.5 text-sm font-mono font-medium text-text transition-all duration-200 hover:border-primary/40 focus:border-primary focus:ring-2 focus:ring-primary/10" placeholder='{ "voice_id": "..." }' />
                    <p v-if="form.errors.voice_settings" class="text-[11px] font-semibold text-red-500">{{ form.errors.voice_settings }}</p>
                </label>

                <!-- Active toggle -->
                <label class="flex items-center gap-3 rounded-xl bg-background px-4 py-3 cursor-pointer transition-colors hover:bg-slate-100">
                    <div class="relative">
                        <input v-model="form.is_active" type="checkbox" class="peer sr-only">
                        <div class="h-6 w-11 rounded-full bg-slate-200 transition-colors peer-checked:bg-primary"></div>
                        <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <span class="text-sm font-semibold text-text">Active</span>
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
.af-card-in { animation: af-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes af-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
