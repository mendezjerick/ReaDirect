<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { User, GraduationCap, Building2, BookOpen, GitMerge, Save, ArrowLeft, KeySquare } from 'lucide-vue-next';

const props = defineProps({
    learner: Object,
    schools: Array,
    classes: Array,
    modules: Array,
});

const form = useForm({
    school_id: props.learner?.school_id ?? '',
    class_id: props.learner?.class_id ?? '',
    current_module_id: props.learner?.current_module_id ?? '',
    learner_code: props.learner?.learner_code ?? '',
    first_name: props.learner?.first_name ?? '',
    last_name: props.learner?.last_name ?? '',
    grade_level: props.learner?.grade_level ?? 'Grade 1',
    current_stage: props.learner?.current_stage ?? 'new',
});

const submit = () => {
    if (props.learner) {
        form.put(`/admin/learners/${props.learner.public_id}`);
        return;
    }
    form.post('/admin/learners');
};
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/admin/learners" class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600">
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <h1 class="text-2xl font-extrabold text-text">{{ learner ? 'Edit Learner' : 'Create Learner' }}</h1>
                    <p class="mt-1 text-sm font-medium text-muted">{{ learner ? 'Update learner information' : 'Register a new learner in the system' }}</p>
                </div>
            </div>
        </div>

        <div class="max-w-4xl">
            <DashboardCard class="in-card">
                <div class="mb-6 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-500">
                        <User class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Learner Details</h2>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <!-- Learner Code -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Learner code</label>
                            <div class="relative">
                                <KeySquare class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.learner_code" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Auto-generated if blank">
                            </div>
                            <span v-if="form.errors.learner_code" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.learner_code }}</span>
                        </div>
                        
                        <!-- Grade Level -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Grade level</label>
                            <div class="relative">
                                <GraduationCap class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.grade_level" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Grade level">
                            </div>
                            <span v-if="form.errors.grade_level" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.grade_level }}</span>
                        </div>

                        <!-- First Name -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">First name</label>
                            <div class="relative">
                                <User class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.first_name" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="First name">
                            </div>
                            <span v-if="form.errors.first_name" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.first_name }}</span>
                        </div>

                        <!-- Last Name -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Last name</label>
                            <div class="relative">
                                <User class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.last_name" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Last name">
                            </div>
                            <span v-if="form.errors.last_name" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.last_name }}</span>
                        </div>

                        <!-- School -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">School</label>
                            <div class="relative">
                                <Building2 class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <select v-model="form.school_id" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40 appearance-none">
                                    <option value="">Select school...</option>
                                    <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                                </select>
                            </div>
                            <span v-if="form.errors.school_id" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.school_id }}</span>
                        </div>

                        <!-- Class -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Class</label>
                            <div class="relative">
                                <GraduationCap class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <select v-model="form.class_id" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40 appearance-none">
                                    <option value="">Select class...</option>
                                    <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.school?.name }} - {{ item.name }}</option>
                                </select>
                            </div>
                            <span v-if="form.errors.class_id" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.class_id }}</span>
                        </div>

                        <!-- Current Module -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Current module</label>
                            <div class="relative">
                                <BookOpen class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <select v-model="form.current_module_id" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40 appearance-none">
                                    <option value="">No module</option>
                                    <option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option>
                                </select>
                            </div>
                            <span v-if="form.errors.current_module_id" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.current_module_id }}</span>
                        </div>

                        <!-- Current Stage -->
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Current stage</label>
                            <div class="relative">
                                <GitMerge class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                <input v-model="form.current_stage" class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40" placeholder="Current stage">
                            </div>
                            <span v-if="form.errors.current_stage" class="mt-1 block text-[11px] font-bold text-red-500">{{ form.errors.current_stage }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link href="/admin/learners" class="rounded-xl px-5 py-2.5 text-[13px] font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60"
                        >
                            <Save class="size-4" />
                            {{ form.processing ? 'Saving...' : 'Save Learner' }}
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
