<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import DashboardCard from '../../../Components/DashboardCard.vue';
import { Pencil, GraduationCap, Mail, Users } from 'lucide-vue-next';

defineProps({ teacher: Object, classes: Array });
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">{{ teacher.name }}</h1>
                <p class="mt-1 flex items-center gap-1.5 text-sm font-medium text-muted">
                    <Mail class="size-4" />
                    {{ teacher.email }}
                </p>
            </div>
            <Link :href="`/admin/teachers/${teacher.id}/edit`" class="group inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-[13px] font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97]">
                <Pencil class="size-4" />
                Edit Teacher
            </Link>
        </div>

        <div class="grid gap-6">
            <!-- Assigned Classes Card -->
            <DashboardCard class="in-card" style="--delay: 0ms">
                <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                        <GraduationCap class="size-4" />
                    </div>
                    <h2 class="text-[15px] font-bold text-text">Assigned Classes</h2>
                </div>
                <div class="space-y-3">
                    <div v-for="item in classes" :key="item.id" class="flex flex-col sm:flex-row sm:items-center justify-between rounded-xl border border-border/40 bg-surface p-3 transition-colors hover:border-border/80 gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-50 text-slate-500">
                                <Users class="size-4" />
                            </div>
                            <div>
                                <p class="text-[13px] font-bold text-text">{{ item.name }}</p>
                                <p class="text-[11px] font-medium text-muted">{{ item.school?.name }}</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600 whitespace-nowrap">{{ item.learners_count }} learners</span>
                    </div>
                    <div v-if="!classes?.length" class="py-4 text-center text-sm font-medium text-muted">No classes assigned.</div>
                </div>
            </DashboardCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.in-card { animation: entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--delay, 0ms); }
@keyframes entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
