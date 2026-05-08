<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';

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
        <h1 class="mb-5 text-3xl font-black">{{ learner ? 'Edit Learner' : 'Create Learner' }}</h1>

        <DashboardCard>
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Learner code</span>
                    <input v-model="form.learner_code" class="rounded-xl border p-3" placeholder="Auto-generated if blank">
                    <span v-if="form.errors.learner_code" class="text-sm font-bold text-danger">{{ form.errors.learner_code }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">First name</span>
                    <input v-model="form.first_name" class="rounded-xl border p-3" placeholder="First name">
                    <span v-if="form.errors.first_name" class="text-sm font-bold text-danger">{{ form.errors.first_name }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Last name</span>
                    <input v-model="form.last_name" class="rounded-xl border p-3" placeholder="Last name">
                    <span v-if="form.errors.last_name" class="text-sm font-bold text-danger">{{ form.errors.last_name }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Grade level</span>
                    <input v-model="form.grade_level" class="rounded-xl border p-3" placeholder="Grade level">
                    <span v-if="form.errors.grade_level" class="text-sm font-bold text-danger">{{ form.errors.grade_level }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">School</span>
                    <select v-model="form.school_id" class="rounded-xl border p-3">
                        <option value="">School</option>
                        <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                    </select>
                    <span v-if="form.errors.school_id" class="text-sm font-bold text-danger">{{ form.errors.school_id }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Class</span>
                    <select v-model="form.class_id" class="rounded-xl border p-3">
                        <option value="">Class</option>
                        <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.school?.name }} - {{ item.name }}</option>
                    </select>
                    <span v-if="form.errors.class_id" class="text-sm font-bold text-danger">{{ form.errors.class_id }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Current module</span>
                    <select v-model="form.current_module_id" class="rounded-xl border p-3">
                        <option value="">No module</option>
                        <option v-for="module in modules" :key="module.id" :value="module.id">{{ module.title }}</option>
                    </select>
                    <span v-if="form.errors.current_module_id" class="text-sm font-bold text-danger">{{ form.errors.current_module_id }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Current stage</span>
                    <input v-model="form.current_stage" class="rounded-xl border p-3" placeholder="Current stage">
                    <span v-if="form.errors.current_stage" class="text-sm font-bold text-danger">{{ form.errors.current_stage }}</span>
                </label>

                <button type="submit" class="rounded-xl bg-primary px-4 py-3 font-black text-white disabled:opacity-60 md:col-span-2" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save' }}
                </button>
            </form>
        </DashboardCard>
    </AdminLayout>
</template>
