<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import DashboardCard from '../../Components/DashboardCard.vue';

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
        <PageHeader title="Create Learner" subtitle="Add a learner to one of your assigned classes" />

        <DashboardCard>
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Learner code</span>
                    <input v-model="form.learner_code" class="rounded-xl border border-border/60 bg-background p-3" placeholder="Auto-generated if blank">
                    <span v-if="form.errors.learner_code" class="text-sm font-bold text-danger">{{ form.errors.learner_code }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Class</span>
                    <select v-model="form.class_id" class="rounded-xl border border-border/60 bg-background p-3">
                        <option value="">Select class</option>
                        <option v-for="item in classes" :key="item.id" :value="item.id">
                            {{ item.school?.name }} - {{ item.name }}
                        </option>
                    </select>
                    <span v-if="form.errors.class_id" class="text-sm font-bold text-danger">{{ form.errors.class_id }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">First name</span>
                    <input v-model="form.first_name" class="rounded-xl border border-border/60 bg-background p-3" placeholder="First name">
                    <span v-if="form.errors.first_name" class="text-sm font-bold text-danger">{{ form.errors.first_name }}</span>
                </label>

                <label class="grid gap-1">
                    <span class="text-sm font-bold text-muted">Last name</span>
                    <input v-model="form.last_name" class="rounded-xl border border-border/60 bg-background p-3" placeholder="Last name">
                    <span v-if="form.errors.last_name" class="text-sm font-bold text-danger">{{ form.errors.last_name }}</span>
                </label>

                <label class="grid gap-1 md:col-span-2">
                    <span class="text-sm font-bold text-muted">Grade level</span>
                    <input v-model="form.grade_level" class="rounded-xl border border-border/60 bg-background p-3" placeholder="Grade level">
                    <span v-if="form.errors.grade_level" class="text-sm font-bold text-danger">{{ form.errors.grade_level }}</span>
                </label>

                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <button type="submit" class="rounded-xl bg-orange-500 px-4 py-3 font-black text-white transition-colors hover:bg-orange-600 disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Learner' }}
                    </button>
                    <Link href="/teacher/learners" class="rounded-xl bg-background px-4 py-3 font-black text-muted">Cancel</Link>
                </div>
            </form>
        </DashboardCard>
    </TeacherLayout>
</template>
