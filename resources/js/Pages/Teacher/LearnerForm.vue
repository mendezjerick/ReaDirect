<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import { 
    ArrowLeft, 
    User, 
    Users, 
    Hash, 
    GraduationCap, 
    Layers, 
    Save 
} from 'lucide-vue-next';

defineProps({
    classes: Array,
});

const form = useForm({
    learner_code: '',
    first_name: '',
    last_name: '',
    grade_level: 'Grade 1',
    class_id: '',
});

const submit = () => form.post('/teacher/learners');
</script>

<template>
    <TeacherLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/teacher/learners" class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-extrabold text-text">Create Learner</h1>
                    <p class="mt-1 text-sm font-medium text-muted">Add a learner to one of your assigned classes</p>
                </div>
            </div>
        </div>

        <div class="max-w-3xl">
            <DashboardCard class="in-card">
                <div class="mb-6 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                        <Users class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Learner Information</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Learner code</label>
                            <div class="relative">
                                <Hash class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input 
                                    v-model="form.learner_code" 
                                    class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10 transition-all duration-200 hover:border-orange-500/40" 
                                    placeholder="Auto-generated if blank" 
                                />
                            </div>
                            <span v-if="form.errors.learner_code" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ form.errors.learner_code }}</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Class</label>
                            <div class="relative">
                                <GraduationCap class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <select 
                                    v-model="form.class_id" 
                                    class="w-full appearance-none rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10 transition-all duration-200 hover:border-orange-500/40"
                                >
                                    <option value="" disabled>Select class</option>
                                    <option v-for="item in classes" :key="item.id" :value="item.id">
                                        {{ item.school?.name }} - {{ item.name }}
                                    </option>
                                </select>
                            </div>
                            <span v-if="form.errors.class_id" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ form.errors.class_id }}</span>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">First name</label>
                            <div class="relative">
                                <User class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input 
                                    v-model="form.first_name" 
                                    class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10 transition-all duration-200 hover:border-orange-500/40" 
                                    placeholder="First name" 
                                />
                            </div>
                            <span v-if="form.errors.first_name" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ form.errors.first_name }}</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Last name</label>
                            <div class="relative">
                                <User class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input 
                                    v-model="form.last_name" 
                                    class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10 transition-all duration-200 hover:border-orange-500/40" 
                                    placeholder="Last name" 
                                />
                            </div>
                            <span v-if="form.errors.last_name" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ form.errors.last_name }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Grade level</label>
                        <div class="relative">
                            <Layers class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input 
                                v-model="form.grade_level" 
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-orange-500 focus:ring-2 focus:ring-orange-500/10 transition-all duration-200 hover:border-orange-500/40" 
                                placeholder="Grade level" 
                            />
                        </div>
                        <span v-if="form.errors.grade_level" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ form.errors.grade_level }}</span>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                        <Link href="/teacher/learners" class="rounded-xl px-5 py-2.5 text-[13px] font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
                            Cancel
                        </Link>
                        <button 
                            type="submit" 
                            class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-orange-600 hover:shadow-md hover:shadow-orange-500/20 active:scale-[0.97] disabled:opacity-60" 
                            :disabled="form.processing"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Saving...' : 'Save Learner' }}
                        </button>
                    </div>
                </form>
            </DashboardCard>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.in-card { animation: entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; }
@keyframes entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
