<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { FileQuestion, Hash, Type, FileText, CheckCircle, Code, Save, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({ item: Object });
const form = useForm({
    content_type: props.item?.content_type ?? '',
    title: props.item?.title ?? '',
    prompt: props.item?.prompt ?? '',
    difficulty: props.item?.difficulty ?? 'grade_1',
    accepted_answers: props.item?.accepted_answers ? JSON.stringify(props.item.accepted_answers, null, 2) : '',
    payload: props.item?.payload ? JSON.stringify(props.item.payload, null, 2) : '',
    is_active: props.item?.is_active ?? true
});
const submit = () => props.item ? form.put(`/admin/assessment-content/${props.item.id}`) : form.post('/admin/assessment-content');
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/admin/assessment-content" class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-extrabold text-text">{{ item ? 'Edit Assessment Content' : 'Create Assessment Content' }}</h1>
                    <p class="mt-1 text-sm font-medium text-muted">{{ item ? 'Update content information' : 'Add new assessment content' }}</p>
                </div>
            </div>
        </div>

        <div class="max-w-3xl">
            <DashboardCard class="in-card">
                <div class="mb-6 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-500">
                        <FileQuestion class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Content Configuration</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <!-- Title -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Title</label>
                            <div class="relative">
                                <Type class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.title" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Title">
                            </div>
                        </div>

                        <!-- Content Type -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Content Type</label>
                            <div class="relative">
                                <Hash class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.content_type" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Content type">
                            </div>
                        </div>

                        <!-- Difficulty -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Difficulty</label>
                            <div class="relative">
                                <CheckCircle class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.difficulty" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Difficulty">
                            </div>
                        </div>

                        <!-- Prompt -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Prompt</label>
                            <div class="relative">
                                <FileText class="size-4 absolute left-3.5 top-3.5 text-slate-400 pointer-events-none" />
                                <textarea v-model="form.prompt" rows="3" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Prompt text"></textarea>
                            </div>
                        </div>

                        <!-- Accepted Answers -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Accepted Answers (JSON or pipe-separated)</label>
                            <div class="relative">
                                <Code class="size-4 absolute left-3.5 top-3.5 text-slate-400 pointer-events-none" />
                                <textarea v-model="form.accepted_answers" rows="4" class="w-full font-mono rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder='["answer1", "answer2"]'></textarea>
                            </div>
                        </div>

                        <!-- Payload -->
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Payload (JSON)</label>
                            <div class="relative">
                                <Code class="size-4 absolute left-3.5 top-3.5 text-slate-400 pointer-events-none" />
                                <textarea v-model="form.payload" rows="6" class="w-full font-mono rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder='{"key": "value"}'></textarea>
                            </div>
                        </div>

                        <!-- Active Toggle -->
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-border/60 text-primary focus:ring-primary/20">
                                <span class="text-[13px] font-bold text-text">Active Content</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link href="/admin/assessment-content" class="rounded-xl px-5 py-2.5 text-[13px] font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Saving...' : 'Save Content' }}
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
