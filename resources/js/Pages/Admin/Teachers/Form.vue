<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { User, Mail, GraduationCap, Save, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({ teacher: Object, classes: Array });
const assigned = props.teacher?.teaching_classes?.map((item) => item.id) ?? [];
const form = useForm({ name: props.teacher?.name ?? '', email: props.teacher?.email ?? '', class_ids: assigned });
const submit = () => props.teacher ? form.put(`/admin/teachers/${props.teacher.id}`) : form.post('/admin/teachers');
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/admin/teachers" class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-extrabold text-text">{{ teacher ? 'Edit Teacher' : 'Create Teacher' }}</h1>
                    <p class="mt-1 text-sm font-medium text-muted">{{ teacher ? 'Update teacher information' : 'Add a new teacher to the system' }}</p>
                </div>
            </div>
        </div>

        <div class="max-w-2xl">
            <DashboardCard class="in-card">
                <div class="mb-6 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <User class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Teacher Details</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Teacher Name</label>
                        <div class="relative">
                            <User class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40"
                                placeholder="Full name"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Email Address</label>
                        <div class="relative">
                            <Mail class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40"
                                placeholder="email@example.com"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Assigned Classes</label>
                        <div class="relative">
                            <GraduationCap class="size-4 absolute left-3.5 top-3.5 text-slate-400 pointer-events-none" />
                            <select
                                v-model="form.class_ids"
                                multiple
                                class="w-full min-h-40 rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40"
                            >
                                <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.school?.name }} - {{ item.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link href="/admin/teachers" class="rounded-xl px-5 py-2.5 text-[13px] font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Saving...' : 'Save Teacher' }}
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
