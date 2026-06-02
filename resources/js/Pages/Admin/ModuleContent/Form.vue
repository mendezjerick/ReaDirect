<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { BookOpen, Layers, Type, Hash, Code, Save, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({ activity: Object, modules: Array });
const form = useForm({
    module_id: props.activity?.module_id ?? '',
    sequence: props.activity?.sequence ?? 1,
    activity_type: props.activity?.activity_type ?? '',
    title: props.activity?.title ?? '',
    configuration: props.activity?.configuration ? JSON.stringify(props.activity.configuration, null, 2) : '',
    is_active: props.activity?.configuration?.is_active ?? true
});
const submit = () => props.activity ? form.put(`/admin/module-content/${props.activity.id}`) : form.post('/admin/module-content');
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/admin/module-content" class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-extrabold text-text">{{ activity ? 'Edit Module Activity' : 'Create Module Activity' }}</h1>
                    <p class="mt-1 text-sm font-medium text-muted">{{ activity ? 'Update activity configuration' : 'Add new activity to a module' }}</p>
                </div>
            </div>
        </div>

        <div class="max-w-3xl">
            <DashboardCard class="in-card">
                <div class="mb-6 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                        <Layers class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Activity Configuration</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <!-- Module -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Module</label>
                            <div class="relative">
                                <BookOpen class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <select v-model="form.module_id" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40 appearance-none">
                                    <option value="">Select module...</option>
                                    <option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Sequence -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Sequence</label>
                            <div class="relative">
                                <Hash class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.sequence" type="number" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="1">
                            </div>
                        </div>

                        <!-- Title -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Title</label>
                            <div class="relative">
                                <Type class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.title" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Title">
                            </div>
                        </div>

                        <!-- Activity Type -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Activity Type</label>
                            <div class="relative">
                                <Layers class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.activity_type" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="E.g., video, quiz">
                            </div>
                        </div>

                        <!-- Configuration JSON -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Configuration (JSON)</label>
                            <div class="relative">
                                <Code class="size-4 absolute left-3.5 top-3.5 text-slate-400 pointer-events-none" />
                                <textarea v-model="form.configuration" rows="8" class="w-full font-mono rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder='{"key": "value"}'></textarea>
                            </div>
                        </div>

                        <!-- Active Toggle -->
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-border/60 text-primary focus:ring-primary/20">
                                <span class="text-[13px] font-bold text-text">Active Activity</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link href="/admin/module-content" class="rounded-xl px-5 py-2.5 text-[13px] font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Saving...' : 'Save Activity' }}
                        </button>
                    </div>
                </form>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.in-card { animation: entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
